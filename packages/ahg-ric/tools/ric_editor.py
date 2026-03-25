#!/usr/bin/env python3
"""RiC Record Editor API"""

from flask import Flask, request, jsonify
from flask_cors import CORS
import requests
import uuid

app = Flask(__name__)
CORS(app)

FUSEKI_ENDPOINT = "http://192.168.0.112:3030/ric"
FUSEKI_QUERY = f"{FUSEKI_ENDPOINT}/query"
FUSEKI_UPDATE = f"{FUSEKI_ENDPOINT}/update"
FUSEKI_AUTH = ('admin', 'admin123')
BASE_URI = "https://archives.theahg.co.za/ric/standalone"

PREFIXES = """
PREFIX rico: <https://www.ica.org/standards/RiC/ontology#>
PREFIX rdf: <http://www.w3.org/1999/02/22-rdf-syntax-ns#>
PREFIX rdfs: <http://www.w3.org/2000/01/rdf-schema#>
"""

ENTITY_TYPES = {
    'RecordSet': {'class': 'rico:RecordSet', 'properties': ['title', 'identifier', 'scopeAndContent', 'history', 'arrangement'], 'relations': ['hasCreator', 'hasAccumulator', 'isOrWasHeldBy', 'includes']},
    'Record': {'class': 'rico:Record', 'properties': ['title', 'identifier', 'scopeAndContent'], 'relations': ['hasCreator', 'isOrWasIncludedIn']},
    'Person': {'class': 'rico:Person', 'properties': ['name', 'history', 'hasBeginningDate', 'hasEndDate'], 'relations': ['isCreatorOf', 'isAssociatedWith']},
    'Family': {'class': 'rico:Family', 'properties': ['name', 'history'], 'relations': ['isCreatorOf', 'hasOrHadMember']},
    'CorporateBody': {'class': 'rico:CorporateBody', 'properties': ['name', 'history'], 'relations': ['isCreatorOf', 'hasOrHadSubordinate']},
    'Place': {'class': 'rico:Place', 'properties': ['name', 'coordinates'], 'relations': ['isOrWasPlaceOfOriginOf']},
    'Activity': {'class': 'rico:Activity', 'properties': ['name', 'description', 'hasActivityType'], 'relations': ['hasOrHadParticipant', 'resultsIn']}
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
        r = requests.post(FUSEKI_UPDATE, data=PREFIXES + update,
            headers={'Content-Type': 'application/sparql-update'},
            auth=FUSEKI_AUTH)
        print(f"Update: {r.status_code}")
        return r.ok
    except Exception as e:
        print(f"Update error: {e}")
        return False

@app.route('/api/editor/health')
def health():
    result = sparql_query("SELECT (1 as ?t) WHERE {}")
    return jsonify({'status': 'healthy' if result else 'degraded'})

@app.route('/api/editor/types')
def get_types():
    return jsonify({'types': list(ENTITY_TYPES.keys()), 'details': ENTITY_TYPES})

@app.route('/api/editor/stats')
def get_stats():
    result = sparql_query("SELECT ?type (COUNT(DISTINCT ?e) as ?c) WHERE { ?e a ?type . FILTER(STRSTARTS(STR(?type), 'https://www.ica.org/standards/RiC/ontology#')) } GROUP BY ?type")
    stats = {}
    if result:
        for row in result['results']['bindings']:
            stats[row['type']['value'].split('#')[-1]] = int(row['c']['value'])
    return jsonify({'stats': stats})

@app.route('/api/editor/entities')
def list_entities():
    t = request.args.get('type')
    if t and t in ENTITY_TYPES:
        tf = f"?e a {ENTITY_TYPES[t]['class']} ."
    else:
        tf = "?e a ?type . FILTER(STRSTARTS(STR(?type), 'https://www.ica.org/standards/RiC/ontology#'))"
    query = f"""SELECT DISTINCT ?e ?type ?label ?id WHERE {{ 
        {tf} 
        ?e a ?type . 
        OPTIONAL {{ ?e rico:title ?label }} 
        OPTIONAL {{ ?e rico:hasAgentName/rico:textualValue ?label }}
        OPTIONAL {{ ?e rico:hasPlaceName/rico:textualValue ?label }}
        OPTIONAL {{ ?e rico:name ?label }}
        OPTIONAL {{ ?e rico:identifier ?id }} 
    }} LIMIT 500"""
    result = sparql_query(query)
    entities = []
    seen = set()
    if result:
        for row in result['results']['bindings']:
            uri = row['e']['value']
            if uri not in seen:
                seen.add(uri)
                entities.append({
                    'uri': uri,
                    'type': row['type']['value'].split('#')[-1],
                    'label': row.get('label', {}).get('value', uri.split('/')[-1]),
                    'identifier': row.get('id', {}).get('value', '')
                })
    return jsonify({'entities': entities, 'count': len(entities)})

@app.route('/api/editor/entity/<path:uri>', methods=['GET'])
def get_entity(uri):
    if not uri.startswith('http'):
        uri = f"https://{uri}"
    
    # Get entity type and properties with proper path traversal for RiC-O
    query = f"""
    SELECT ?type ?title ?identifier ?scopeAndContent ?history ?arrangement
           ?agentName ?placeName ?name ?description ?activityType
           ?beginDate ?endDate ?coordinates
    WHERE {{
        <{uri}> a ?type .
        OPTIONAL {{ <{uri}> rico:title ?title }}
        OPTIONAL {{ <{uri}> rico:identifier ?identifier }}
        OPTIONAL {{ <{uri}> rico:scopeAndContent ?scopeAndContent }}
        OPTIONAL {{ <{uri}> rico:history ?history }}
        OPTIONAL {{ <{uri}> rico:arrangement ?arrangement }}
        OPTIONAL {{ <{uri}> rico:hasAgentName/rico:textualValue ?agentName }}
        OPTIONAL {{ <{uri}> rico:hasPlaceName/rico:textualValue ?placeName }}
        OPTIONAL {{ <{uri}> rico:name ?name }}
        OPTIONAL {{ <{uri}> rico:description ?description }}
        OPTIONAL {{ <{uri}> rico:hasActivityType ?activityType }}
        OPTIONAL {{ <{uri}> rico:hasBeginningDate ?beginDate }}
        OPTIONAL {{ <{uri}> rico:hasEndDate ?endDate }}
        OPTIONAL {{ <{uri}> rico:coordinates ?coordinates }}
    }}
    LIMIT 1
    """
    
    result = sparql_query(query)
    if not result or not result['results']['bindings']:
        return jsonify({'error': 'Not found'}), 404
    
    row = result['results']['bindings'][0]
    etype = row['type']['value'].split('#')[-1]
    
    # Build properties based on entity type
    props = {}
    if 'title' in row: props['title'] = row['title']['value']
    if 'identifier' in row: props['identifier'] = row['identifier']['value']
    if 'scopeAndContent' in row: props['scopeAndContent'] = row['scopeAndContent']['value']
    if 'history' in row: props['history'] = row['history']['value']
    if 'arrangement' in row: props['arrangement'] = row['arrangement']['value']
    if 'agentName' in row: props['name'] = row['agentName']['value']
    if 'placeName' in row: props['name'] = row['placeName']['value']
    if 'name' in row: props['name'] = row['name']['value']
    if 'description' in row: props['description'] = row['description']['value']
    if 'activityType' in row: props['hasActivityType'] = row['activityType']['value']
    if 'beginDate' in row: props['hasBeginningDate'] = row['beginDate']['value']
    if 'endDate' in row: props['hasEndDate'] = row['endDate']['value']
    if 'coordinates' in row: props['coordinates'] = row['coordinates']['value']
    
    # Get relations
    rel_query = f"""
    SELECT ?p ?target ?targetLabel WHERE {{
        <{uri}> ?p ?target .
        ?target a ?targetType .
        FILTER(STRSTARTS(STR(?targetType), 'https://www.ica.org/standards/RiC/ontology#'))
        FILTER(?p != rdf:type)
        OPTIONAL {{ ?target rico:title ?targetLabel }}
        OPTIONAL {{ ?target rico:hasAgentName/rico:textualValue ?targetLabel }}
    }}
    """
    rel_result = sparql_query(rel_query)
    rels = []
    if rel_result:
        for r in rel_result['results']['bindings']:
            rels.append({
                'predicate': r['p']['value'].split('#')[-1],
                'targetUri': r['target']['value'],
                'targetLabel': r.get('targetLabel', {}).get('value', r['target']['value'].split('/')[-1])
            })
    
    return jsonify({'uri': uri, 'type': etype, 'properties': props, 'relations': rels})

@app.route('/api/editor/entity', methods=['POST'])
def create_entity():
    data = request.json
    t = data.get('type')
    props = data.get('properties', {})
    rels = data.get('relations', [])
    if t not in ENTITY_TYPES:
        return jsonify({'error': 'Invalid type'}), 400
    
    uri = f"{BASE_URI}/{t.lower()}/{str(uuid.uuid4())[:8]}"
    triples = [f"<{uri}> a {ENTITY_TYPES[t]['class']}"]
    
    for p, v in props.items():
        if v:
            v_escaped = v.replace('\\', '\\\\').replace('"', '\\"').replace('\n', '\\n')
            if p == 'name':
                # Handle name based on entity type
                if t in ['Person', 'Family', 'CorporateBody']:
                    nu = f"{uri}/name"
                    triples.append(f'<{uri}> rico:hasAgentName <{nu}>')
                    triples.append(f'<{nu}> a rico:AgentName')
                    triples.append(f'<{nu}> rico:textualValue "{v_escaped}"')
                elif t == 'Place':
                    nu = f"{uri}/name"
                    triples.append(f'<{uri}> rico:hasPlaceName <{nu}>')
                    triples.append(f'<{nu}> a rico:PlaceName')
                    triples.append(f'<{nu}> rico:textualValue "{v_escaped}"')
                else:
                    triples.append(f'<{uri}> rico:name "{v_escaped}"')
            else:
                triples.append(f'<{uri}> rico:{p} "{v_escaped}"')
    
    for r in rels:
        if r.get('predicate') and r.get('targetUri'):
            triples.append(f"<{uri}> rico:{r['predicate']} <{r['targetUri']}>")
    
    update = "INSERT DATA { " + " . ".join(triples) + " . }"
    print(f"Creating: {update[:300]}")
    if sparql_update(update):
        return jsonify({'success': True, 'uri': uri})
    return jsonify({'error': 'Failed to create'}), 500

@app.route('/api/editor/entity/update', methods=['POST'])
def update_entity():
    data = request.json
    uri = data.get('uri')
    props = data.get('properties', {})
    etype = data.get('type', '')
    
    if not uri:
        return jsonify({'error': 'URI required'}), 400
    
    # Delete existing literal properties and name nodes
    sparql_update(f"DELETE WHERE {{ <{uri}> rico:title ?o }}")
    sparql_update(f"DELETE WHERE {{ <{uri}> rico:identifier ?o }}")
    sparql_update(f"DELETE WHERE {{ <{uri}> rico:scopeAndContent ?o }}")
    sparql_update(f"DELETE WHERE {{ <{uri}> rico:history ?o }}")
    sparql_update(f"DELETE WHERE {{ <{uri}> rico:arrangement ?o }}")
    sparql_update(f"DELETE WHERE {{ <{uri}> rico:name ?o }}")
    sparql_update(f"DELETE WHERE {{ <{uri}> rico:description ?o }}")
    sparql_update(f"DELETE WHERE {{ <{uri}> rico:hasActivityType ?o }}")
    sparql_update(f"DELETE WHERE {{ <{uri}> rico:hasAgentName ?n . ?n ?p ?o }}")
    sparql_update(f"DELETE WHERE {{ <{uri}> rico:hasPlaceName ?n . ?n ?p ?o }}")
    
    # Insert new values
    triples = []
    for p, v in props.items():
        if v:
            v_escaped = v.replace('\\', '\\\\').replace('"', '\\"').replace('\n', '\\n')
            if p == 'name':
                if etype in ['Person', 'Family', 'CorporateBody']:
                    nu = f"{uri}/name/{str(uuid.uuid4())[:4]}"
                    triples.append(f'<{uri}> rico:hasAgentName <{nu}>')
                    triples.append(f'<{nu}> a rico:AgentName')
                    triples.append(f'<{nu}> rico:textualValue "{v_escaped}"')
                elif etype == 'Place':
                    nu = f"{uri}/name/{str(uuid.uuid4())[:4]}"
                    triples.append(f'<{uri}> rico:hasPlaceName <{nu}>')
                    triples.append(f'<{nu}> a rico:PlaceName')
                    triples.append(f'<{nu}> rico:textualValue "{v_escaped}"')
                else:
                    triples.append(f'<{uri}> rico:name "{v_escaped}"')
            else:
                triples.append(f'<{uri}> rico:{p} "{v_escaped}"')
    
    if triples:
        insert_q = "INSERT DATA { " + " . ".join(triples) + " . }"
        print(f"Updating: {insert_q[:300]}")
        sparql_update(insert_q)
    
    return jsonify({'success': True})

@app.route('/api/editor/entity/delete', methods=['POST'])
def delete_entity():
    data = request.json
    uri = data.get('uri')
    if not uri:
        return jsonify({'error': 'URI required'}), 400
    
    # Delete name nodes first
    sparql_update(f"DELETE WHERE {{ <{uri}> rico:hasAgentName ?n . ?n ?p ?o }}")
    sparql_update(f"DELETE WHERE {{ <{uri}> rico:hasPlaceName ?n . ?n ?p ?o }}")
    # Delete the entity
    sparql_update(f"DELETE WHERE {{ <{uri}> ?p ?o }}")
    sparql_update(f"DELETE WHERE {{ ?s ?p <{uri}> }}")
    return jsonify({'success': True})

@app.route('/api/editor/search')
def search():
    q = request.args.get('q', '')
    if len(q) < 2:
        return jsonify({'results': []})
    query = f"""SELECT DISTINCT ?e ?type ?label WHERE {{
        ?e a ?type .
        {{ ?e rico:title ?label }} 
        UNION {{ ?e rico:hasAgentName/rico:textualValue ?label }}
        UNION {{ ?e rico:hasPlaceName/rico:textualValue ?label }}
        UNION {{ ?e rico:name ?label }}
        FILTER(CONTAINS(LCASE(?label), LCASE("{q}")))
        FILTER(STRSTARTS(STR(?type), 'https://www.ica.org/standards/RiC/ontology#'))
    }} LIMIT 20"""
    result = sparql_query(query)
    results = []
    if result:
        for r in result['results']['bindings']:
            results.append({'uri': r['e']['value'], 'type': r['type']['value'].split('#')[-1], 'label': r['label']['value']})
    return jsonify({'results': results})


@app.route('/api/editor/relationship', methods=['POST', 'DELETE'])
def handle_relationship():
    """Create or delete a relationship between entities"""
    data = request.get_json()
    source = data.get('source')
    target = data.get('target')
    predicate = data.get('predicate')
    
    if not all([source, target, predicate]):
        return jsonify({'success': False, 'error': 'Missing source, target, or predicate'})
    
    if request.method == 'POST':
        # Create relationship
        sparql = f"""
            PREFIX rico: <https://www.ica.org/standards/RiC/ontology#>
            INSERT DATA {{
                <{source}> rico:{predicate} <{target}> .
            }}
        """
    else:
        # Delete relationship
        sparql = f"""
            PREFIX rico: <https://www.ica.org/standards/RiC/ontology#>
            DELETE WHERE {{
                <{source}> rico:{predicate} <{target}> .
            }}
        """
    
    try:
        result = sparql_update(sparql)
        return jsonify({'success': True})
    except Exception as e:
        return jsonify({'success': False, 'error': str(e)})


if __name__ == '__main__':
    print("RiC Editor API starting on port 5002...")
    app.run(host='0.0.0.0', port=5002, debug=True)
