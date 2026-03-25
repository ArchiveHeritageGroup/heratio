#!/usr/bin/env python3
"""
RiC Provenance API - Activity-based provenance tracking
Implements RiC-O Activity model for creation, accumulation, management events
"""

from flask import Flask, request, jsonify
from flask_cors import CORS
import requests
import uuid
from datetime import datetime

app = Flask(__name__)
CORS(app)

FUSEKI_ENDPOINT = "http://192.168.0.112:3030/ric"
FUSEKI_QUERY = f"{FUSEKI_ENDPOINT}/query"
FUSEKI_UPDATE = f"{FUSEKI_ENDPOINT}/update"
BASE_URI = "https://archives.theahg.co.za/ric/standalone"

PREFIXES = """
PREFIX rico: <https://www.ica.org/standards/RiC/ontology#>
PREFIX rdf: <http://www.w3.org/1999/02/22-rdf-syntax-ns#>
PREFIX rdfs: <http://www.w3.org/2000/01/rdf-schema#>
PREFIX xsd: <http://www.w3.org/2001/XMLSchema#>
"""

# RiC-O Activity Types
ACTIVITY_TYPES = {
    'Creation': {
        'class': 'rico:Activity',
        'subtype': 'Creation',
        'description': 'The creation of a record or record set',
        'icon': 'bi-plus-circle',
        'color': '#28a745'
    },
    'Accumulation': {
        'class': 'rico:Activity',
        'subtype': 'Accumulation', 
        'description': 'The accumulation/collection of records over time',
        'icon': 'bi-collection',
        'color': '#17a2b8'
    },
    'Management': {
        'class': 'rico:Activity',
        'subtype': 'Management',
        'description': 'Custody, arrangement, or other management activities',
        'icon': 'bi-gear',
        'color': '#ffc107'
    },
    'Transfer': {
        'class': 'rico:Activity',
        'subtype': 'Transfer',
        'description': 'Transfer of custody or ownership',
        'icon': 'bi-arrow-left-right',
        'color': '#6f42c1'
    },
    'Modification': {
        'class': 'rico:Activity',
        'subtype': 'Modification',
        'description': 'Modification, addition, or deletion of records',
        'icon': 'bi-pencil',
        'color': '#fd7e14'
    },
    'Description': {
        'class': 'rico:Activity',
        'subtype': 'Description',
        'description': 'Archival description or cataloguing',
        'icon': 'bi-card-text',
        'color': '#20c997'
    },
    'Digitization': {
        'class': 'rico:Activity',
        'subtype': 'Digitization',
        'description': 'Digital capture or conversion',
        'icon': 'bi-camera',
        'color': '#e83e8c'
    },
    'Preservation': {
        'class': 'rico:Activity',
        'subtype': 'Preservation',
        'description': 'Conservation or preservation actions',
        'icon': 'bi-shield-check',
        'color': '#6c757d'
    }
}

def sparql_query(query):
    try:
        r = requests.post(FUSEKI_QUERY, data=PREFIXES + query,
            headers={'Content-Type': 'application/sparql-query', 'Accept': 'application/json'})
        return r.json() if r.ok else None
    except Exception as e:
        print(f"Query error: {e}")
        return None

def sparql_update(update):
    try:
        r = requests.post(FUSEKI_UPDATE, data=PREFIXES + update, auth=('admin', 'admin123'),
            headers={'Content-Type': 'application/sparql-update'})
        return r.ok
    except Exception as e:
        print(f"Update error: {e}")
        return False

@app.route('/api/provenance/health')
def health():
    result = sparql_query("SELECT (1 as ?t) WHERE {}")
    return jsonify({'status': 'healthy' if result else 'degraded'})

@app.route('/api/provenance/activity-types')
def get_activity_types():
    """Get available activity types"""
    return jsonify({'types': ACTIVITY_TYPES})

@app.route('/api/provenance/activities')
def list_activities():
    """List all activities with optional filtering"""
    activity_type = request.args.get('type')
    record_uri = request.args.get('record')
    agent_uri = request.args.get('agent')
    
    filters = []
    if activity_type:
        filters.append(f'?activity rico:hasActivityType "{activity_type}"')
    if record_uri:
        filters.append(f'{{ ?activity rico:resultsIn <{record_uri}> }} UNION {{ ?activity rico:affects <{record_uri}> }}')
    if agent_uri:
        filters.append(f'?activity rico:hasOrHadParticipant <{agent_uri}>')
    
    filter_clause = " . ".join(filters) if filters else ""
    
    query = f"""
    SELECT DISTINCT ?activity ?activityType ?description ?date ?dateStart ?dateEnd
           ?participant ?participantLabel ?participantType
           ?record ?recordLabel
    WHERE {{
        ?activity a rico:Activity .
        OPTIONAL {{ ?activity rico:hasActivityType ?activityType }}
        OPTIONAL {{ ?activity rico:description ?description }}
        OPTIONAL {{ ?activity rico:name ?description }}
        OPTIONAL {{ 
            ?activity rico:isOrWasAssociatedWithDate ?dateNode .
            OPTIONAL {{ ?dateNode rico:expressedDate ?date }}
            OPTIONAL {{ ?dateNode rico:hasBeginningDate ?dateStart }}
            OPTIONAL {{ ?dateNode rico:hasEndDate ?dateEnd }}
        }}
        OPTIONAL {{
            ?activity rico:hasOrHadParticipant ?participant .
            ?participant a ?participantType .
            OPTIONAL {{ ?participant rico:hasAgentName/rico:textualValue ?participantLabel }}
            OPTIONAL {{ ?participant rico:title ?participantLabel }}
        }}
        OPTIONAL {{
            {{ ?activity rico:resultsIn ?record }} UNION {{ ?activity rico:affects ?record }}
            OPTIONAL {{ ?record rico:title ?recordLabel }}
        }}
        {filter_clause}
    }}
    ORDER BY DESC(?dateStart) DESC(?date)
    LIMIT 200
    """
    
    result = sparql_query(query)
    activities = {}
    
    if result:
        for row in result['results']['bindings']:
            uri = row['activity']['value']
            if uri not in activities:
                activities[uri] = {
                    'uri': uri,
                    'type': row.get('activityType', {}).get('value', 'Activity'),
                    'description': row.get('description', {}).get('value', ''),
                    'date': row.get('date', {}).get('value'),
                    'dateStart': row.get('dateStart', {}).get('value'),
                    'dateEnd': row.get('dateEnd', {}).get('value'),
                    'participants': [],
                    'records': []
                }
            
            # Add participant if present
            if 'participant' in row:
                p = {
                    'uri': row['participant']['value'],
                    'label': row.get('participantLabel', {}).get('value', row['participant']['value'].split('/')[-1]),
                    'type': row.get('participantType', {}).get('value', '').split('#')[-1]
                }
                if p not in activities[uri]['participants']:
                    activities[uri]['participants'].append(p)
            
            # Add record if present
            if 'record' in row:
                r = {
                    'uri': row['record']['value'],
                    'label': row.get('recordLabel', {}).get('value', row['record']['value'].split('/')[-1])
                }
                if r not in activities[uri]['records']:
                    activities[uri]['records'].append(r)
    
    return jsonify({'activities': list(activities.values()), 'count': len(activities)})

@app.route('/api/provenance/activity/<path:uri>')
def get_activity(uri):
    """Get detailed activity information"""
    if not uri.startswith('http'):
        uri = f"https://{uri}"
    
    query = f"""
    SELECT ?p ?o ?oLabel WHERE {{
        <{uri}> ?p ?o .
        OPTIONAL {{ ?o rico:title ?oLabel }}
        OPTIONAL {{ ?o rico:hasAgentName/rico:textualValue ?oLabel }}
        OPTIONAL {{ ?o rico:expressedDate ?oLabel }}
    }}
    """
    
    result = sparql_query(query)
    if not result:
        return jsonify({'error': 'Not found'}), 404
    
    activity = {'uri': uri, 'properties': {}, 'relations': []}
    
    for row in result['results']['bindings']:
        p = row['p']['value'].split('#')[-1]
        o = row['o']
        
        if o['type'] == 'uri':
            activity['relations'].append({
                'predicate': p,
                'targetUri': o['value'],
                'targetLabel': row.get('oLabel', {}).get('value', o['value'].split('/')[-1])
            })
        else:
            activity['properties'][p] = o['value']
    
    return jsonify(activity)

@app.route('/api/provenance/activity', methods=['POST'])
def create_activity():
    """Create a new activity with full provenance chain"""
    data = request.json
    
    activity_type = data.get('activityType', 'Activity')
    description = data.get('description', '')
    date_start = data.get('dateStart')
    date_end = data.get('dateEnd')
    date_expressed = data.get('dateExpressed')
    participants = data.get('participants', [])  # List of agent URIs
    records = data.get('records', [])  # List of record URIs
    place_uri = data.get('place')
    
    # Generate URIs
    activity_uri = f"{BASE_URI}/activity/{str(uuid.uuid4())[:8]}"
    date_uri = f"{activity_uri}/date"
    
    triples = [
        f"<{activity_uri}> a rico:Activity",
        f'<{activity_uri}> rico:hasActivityType "{activity_type}"'
    ]
    
    if description:
        desc_escaped = description.replace('"', '\\"').replace('\n', '\\n')
        triples.append(f'<{activity_uri}> rico:description "{desc_escaped}"')
    
    # Add date information
    if date_start or date_end or date_expressed:
        triples.append(f"<{activity_uri}> rico:isOrWasAssociatedWithDate <{date_uri}>")
        triples.append(f"<{date_uri}> a rico:DateRange")
        if date_expressed:
            triples.append(f'<{date_uri}> rico:expressedDate "{date_expressed}"')
        if date_start:
            triples.append(f'<{date_uri}> rico:hasBeginningDate "{date_start}"^^xsd:date')
        if date_end:
            triples.append(f'<{date_uri}> rico:hasEndDate "{date_end}"^^xsd:date')
    
    # Add participants (agents)
    for participant in participants:
        if participant.get('uri'):
            triples.append(f"<{activity_uri}> rico:hasOrHadParticipant <{participant['uri']}>")
            # Add role if specified
            if participant.get('role'):
                role_uri = f"{activity_uri}/role/{str(uuid.uuid4())[:4]}"
                triples.append(f"<{role_uri}> a rico:AgentRole")
                triples.append(f'<{role_uri}> rico:hasRoleType "{participant["role"]}"')
                triples.append(f"<{role_uri}> rico:isOrWasAgentRoleOf <{participant['uri']}>")
                triples.append(f"<{activity_uri}> rico:hasOrHadAgentRole <{role_uri}>")
    
    # Add affected/resulting records
    for record in records:
        if record.get('uri'):
            relation = record.get('relation', 'resultsIn')
            triples.append(f"<{activity_uri}> rico:{relation} <{record['uri']}>")
    
    # Add place
    if place_uri:
        triples.append(f"<{activity_uri}> rico:hasOrHadLocation <{place_uri}>")
    
    # Add provenance metadata
    triples.append(f'<{activity_uri}> rico:isOrWasRegulatedBy <{BASE_URI}/editor>')
    triples.append(f'<{activity_uri}> rdfs:comment "Created via RiC Provenance Editor on {datetime.now().isoformat()}"')
    
    update = "INSERT DATA { " + " . ".join(triples) + " . }"
    
    if sparql_update(update):
        return jsonify({'success': True, 'uri': activity_uri})
    return jsonify({'error': 'Failed to create activity'}), 500

@app.route('/api/provenance/timeline/<path:record_uri>')
def get_record_timeline(record_uri):
    """Get provenance timeline for a specific record"""
    if not record_uri.startswith('http'):
        record_uri = f"https://{record_uri}"
    
    query = f"""
    SELECT ?activity ?activityType ?description ?date ?dateStart ?dateEnd
           ?participant ?participantLabel
    WHERE {{
        {{ ?activity rico:resultsIn <{record_uri}> }}
        UNION {{ ?activity rico:affects <{record_uri}> }}
        UNION {{ <{record_uri}> rico:isOrWasAffectedBy ?activity }}
        
        OPTIONAL {{ ?activity rico:hasActivityType ?activityType }}
        OPTIONAL {{ ?activity rico:description ?description }}
        OPTIONAL {{ 
            ?activity rico:isOrWasAssociatedWithDate ?dateNode .
            OPTIONAL {{ ?dateNode rico:expressedDate ?date }}
            OPTIONAL {{ ?dateNode rico:hasBeginningDate ?dateStart }}
            OPTIONAL {{ ?dateNode rico:hasEndDate ?dateEnd }}
        }}
        OPTIONAL {{
            ?activity rico:hasOrHadParticipant ?participant .
            OPTIONAL {{ ?participant rico:hasAgentName/rico:textualValue ?participantLabel }}
        }}
    }}
    ORDER BY ?dateStart ?date
    """
    
    result = sparql_query(query)
    timeline = []
    seen = set()
    
    if result:
        for row in result['results']['bindings']:
            uri = row['activity']['value']
            if uri in seen:
                continue
            seen.add(uri)
            
            timeline.append({
                'uri': uri,
                'type': row.get('activityType', {}).get('value', 'Activity'),
                'description': row.get('description', {}).get('value', ''),
                'date': row.get('date', {}).get('value') or row.get('dateStart', {}).get('value'),
                'dateStart': row.get('dateStart', {}).get('value'),
                'dateEnd': row.get('dateEnd', {}).get('value'),
                'participant': row.get('participantLabel', {}).get('value')
            })
    
    return jsonify({'record': record_uri, 'timeline': timeline})

@app.route('/api/provenance/agent-activities/<path:agent_uri>')
def get_agent_activities(agent_uri):
    """Get all activities an agent participated in"""
    if not agent_uri.startswith('http'):
        agent_uri = f"https://{agent_uri}"
    
    query = f"""
    SELECT ?activity ?activityType ?description ?date ?record ?recordLabel
    WHERE {{
        ?activity rico:hasOrHadParticipant <{agent_uri}> .
        OPTIONAL {{ ?activity rico:hasActivityType ?activityType }}
        OPTIONAL {{ ?activity rico:description ?description }}
        OPTIONAL {{ 
            ?activity rico:isOrWasAssociatedWithDate/rico:expressedDate ?date
        }}
        OPTIONAL {{
            {{ ?activity rico:resultsIn ?record }} UNION {{ ?activity rico:affects ?record }}
            ?record rico:title ?recordLabel
        }}
    }}
    ORDER BY DESC(?date)
    """
    
    result = sparql_query(query)
    activities = []
    seen = set()
    
    if result:
        for row in result['results']['bindings']:
            uri = row['activity']['value']
            if uri in seen:
                continue
            seen.add(uri)
            
            activities.append({
                'uri': uri,
                'type': row.get('activityType', {}).get('value', 'Activity'),
                'description': row.get('description', {}).get('value', ''),
                'date': row.get('date', {}).get('value'),
                'record': row.get('recordLabel', {}).get('value')
            })
    
    return jsonify({'agent': agent_uri, 'activities': activities})

@app.route('/api/provenance/chain/<path:record_uri>')
def get_provenance_chain(record_uri):
    """Get full provenance chain showing custody/ownership history"""
    if not record_uri.startswith('http'):
        record_uri = f"https://{record_uri}"
    
    query = f"""
    SELECT ?activity ?activityType ?date ?fromAgent ?fromLabel ?toAgent ?toLabel ?place ?placeLabel
    WHERE {{
        {{ ?activity rico:resultsIn <{record_uri}> }}
        UNION {{ ?activity rico:affects <{record_uri}> }}
        
        ?activity rico:hasActivityType ?activityType .
        FILTER(?activityType IN ("Creation", "Transfer", "Accumulation", "Management"))
        
        OPTIONAL {{ 
            ?activity rico:isOrWasAssociatedWithDate/rico:hasBeginningDate ?date
        }}
        OPTIONAL {{
            ?activity rico:hasOrHadParticipant ?fromAgent .
            ?fromAgent rico:hasAgentName/rico:textualValue ?fromLabel .
        }}
        OPTIONAL {{
            ?activity rico:hasOrHadLocation ?place .
            ?place rico:hasPlaceName/rico:textualValue ?placeLabel .
        }}
    }}
    ORDER BY ?date
    """
    
    result = sparql_query(query)
    chain = []
    
    if result:
        for row in result['results']['bindings']:
            chain.append({
                'activity': row['activity']['value'],
                'type': row.get('activityType', {}).get('value'),
                'date': row.get('date', {}).get('value'),
                'agent': row.get('fromLabel', {}).get('value'),
                'agentUri': row.get('fromAgent', {}).get('value'),
                'place': row.get('placeLabel', {}).get('value')
            })
    
    return jsonify({'record': record_uri, 'chain': chain})

if __name__ == '__main__':
    print("RiC Provenance API starting on port 5003...")
    app.run(host='0.0.0.0', port=5003, debug=False)
