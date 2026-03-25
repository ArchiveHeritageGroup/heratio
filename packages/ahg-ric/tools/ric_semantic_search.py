#!/usr/bin/env python3
"""RiC Semantic Search API with Elasticsearch Fuzzy Matching"""

import os
import re
import json
from datetime import datetime
from flask import Flask, jsonify, request
from flask_cors import CORS
import urllib.request

app = Flask(__name__)
CORS(app)

FUSEKI_ENDPOINT = os.environ.get('FUSEKI_ENDPOINT', 'http://192.168.0.112:3030/ric')
FUSEKI_QUERY = f"{FUSEKI_ENDPOINT}/query"
ES_ENDPOINT = os.environ.get('ES_ENDPOINT', 'http://localhost:9200')
ATOM_BASE_URL = os.environ.get('ATOM_BASE_URL', 'https://psis.theahg.co.za')

ES_INDEX_IO = 'atom_psis_qubitinformationobject'
ES_INDEX_ACTOR = 'atom_psis_qubitactor'

PREFIXES = """
PREFIX rico: <https://www.ica.org/standards/RiC/ontology#>
PREFIX rdf: <http://www.w3.org/1999/02/22-rdf-syntax-ns#>
PREFIX rdfs: <http://www.w3.org/2000/01/rdf-schema#>
PREFIX xsd: <http://www.w3.org/2001/XMLSchema#>
"""

# Minimal stop words - keep archival terms!
STOP_WORDS = {'the', 'a', 'an', 'of', 'in', 'on', 'at', 'to', 'for', 'with', 'by', 
              'from', 'and', 'or', 'is', 'are', 'was', 'were', 'be', 'been',
              'have', 'has', 'had', 'do', 'does', 'did', 'will', 'would',
              'give', 'me', 'my', 'i', 'we', 'you', 'your', 'they', 'their',
              'this', 'that', 'all', 'any', 'some', 'no', 'not',
              'show', 'find', 'search', 'get', 'list', 'display'}

def es_search(indices, query_body):
    """Execute Elasticsearch search"""
    try:
        if isinstance(indices, list):
            indices = ','.join(indices)
        url = f"{ES_ENDPOINT}/{indices}/_search"
        data = json.dumps(query_body).encode('utf-8')
        req = urllib.request.Request(url, data=data, 
            headers={'Content-Type': 'application/json'}, method='POST')
        with urllib.request.urlopen(req, timeout=10) as response:
            return json.loads(response.read().decode('utf-8'))
    except Exception as e:
        print(f"ES error: {e}")
        return None

def sparql_query(query):
    """Execute SPARQL query"""
    try:
        data = (PREFIXES + query).encode('utf-8')
        req = urllib.request.Request(FUSEKI_QUERY, data=data,
            headers={'Content-Type': 'application/sparql-query', 'Accept': 'application/json'})
        with urllib.request.urlopen(req, timeout=30) as response:
            return json.loads(response.read().decode('utf-8'))
    except Exception as e:
        print(f"SPARQL error: {e}")
        return None

def extract_terms(query):
    """Extract search terms, keeping important archival words"""
    words = re.findall(r'\b[a-zA-Z]+\b', query.lower())
    return [w for w in words if w not in STOP_WORDS and len(w) > 1]

def build_es_query(search_text):
    """Build comprehensive fuzzy ES query"""
    terms = extract_terms(search_text)
    search_string = ' '.join(terms) if terms else search_text.strip()
    
    return {
        "size": 100,
        "query": {
            "bool": {
                "should": [
                    # Fuzzy multi-match across all text fields
                    {
                        "multi_match": {
                            "query": search_string,
                            "fields": [
                                "i18n.en.title^3",
                                "i18n.af.title^3",
                                "i18n.en.title.autocomplete^2",
                                "identifier^2",
                                "referenceCode^2",
                                "i18n.en.scopeAndContent",
                                "i18n.af.scopeAndContent",
                                "i18n.en.authorizedFormOfName^3",
                                "i18n.af.authorizedFormOfName^3",
                                "creators.i18n.en.authorizedFormOfName^2",
                                "creators.i18n.af.authorizedFormOfName^2"
                            ],
                            "type": "best_fields",
                            "fuzziness": "AUTO",
                            "prefix_length": 1,
                            "operator": "or"
                        }
                    },
                    # Wildcard for partial matching
                    {
                        "query_string": {
                            "query": " OR ".join([f"*{t}*" for t in terms]) if terms else f"*{search_text}*",
                            "fields": ["i18n.en.title", "i18n.af.title", "identifier"],
                            "analyze_wildcard": True
                        }
                    },
                    # Phrase match for exact sequences
                    {
                        "multi_match": {
                            "query": search_string,
                            "fields": ["i18n.en.title", "i18n.af.title"],
                            "type": "phrase",
                            "boost": 2
                        }
                    }
                ],
                "minimum_should_match": 1
            }
        },
        "highlight": {
            "fields": {
                "i18n.en.title": {},
                "i18n.af.title": {},
                "i18n.en.scopeAndContent": {"fragment_size": 150}
            }
        },
        "_source": ["slug", "identifier", "referenceCode", "levelOfDescriptionId",
                    "i18n.en.title", "i18n.af.title", "i18n.en.scopeAndContent",
                    "i18n.en.authorizedFormOfName", "creators", "dates", "repository"]
    }

def format_es_results(es_result, search_terms=None):
    """Format ES results"""
    if not es_result or 'hits' not in es_result:
        return {'results': [], 'count': 0, 'source': 'elasticsearch'}
    
    results = []
    seen = set()
    
    for hit in es_result['hits'].get('hits', []):
        src = hit.get('_source', {})
        slug = src.get('slug', '')
        if slug in seen:
            continue
        seen.add(slug)
        
        i18n = src.get('i18n', {})
        title = i18n.get('en', {}).get('title') or i18n.get('af', {}).get('title') or 'Untitled'
        
        # Get creator names
        creators = []
        for c in src.get('creators', []):
            ci18n = c.get('i18n', {})
            name = ci18n.get('en', {}).get('authorizedFormOfName') or ci18n.get('af', {}).get('authorizedFormOfName')
            if name:
                creators.append(name)
        
        # Get dates
        dates = src.get('dates', [])
        date_str = dates[0].get('date') if dates else None
        
        # Get highlight
        highlights = hit.get('highlight', {})
        highlight = None
        for frags in highlights.values():
            if frags:
                highlight = frags[0]
                break
        
        idx = hit.get('_index', '')
        entity_type = 'Actor' if 'actor' in idx else 'Record'
        
        results.append({
            'uri': f"{ATOM_BASE_URL}/{slug}",
            'atomUrl': f"{ATOM_BASE_URL}/{slug}",
            'slug': slug,
            'type': entity_type,
            'title': title,
            'identifier': src.get('identifier') or src.get('referenceCode'),
            'creator': ', '.join(creators) if creators else None,
            'date': date_str,
            'score': hit.get('_score', 0),
            'highlight': highlight,
            'source': 'elasticsearch'
        })
    
    results.sort(key=lambda x: x.get('score', 0), reverse=True)
    total = es_result['hits'].get('total')
    total = total if isinstance(total, int) else total.get('value', len(results))
    
    return {'results': results, 'count': len(results), 'total': total, 'source': 'elasticsearch'}

def parse_query(query):
    """Parse query intent"""
    q = query.lower().strip()
    q = re.sub(r'^(give\s+me|show\s+me|find|search\s+for|get|list|display)\s+', '', q)
    
    # Only match "all fonds" or "list fonds" - not "pieterse fonds"
    if re.match(r'^(all\s+)?(fonds?|series|collections?)$', q):
        return 'level', {'level': q}
    
    # Date range
    m = re.search(r'(?:from|between|dated?)\s+(\d{4})(?:\s*[-–to]+\s*(\d{4}))?', q)
    if m:
        return 'date', {'start': m.group(1), 'end': m.group(2) or m.group(1)}
    
    # Default: fuzzy full-text search
    return 'fuzzy', {'term': query}

@app.route('/api/search', methods=['POST', 'GET'])
def api_search():
    if request.method == 'POST':
        data = request.get_json() or {}
        query = data.get('query', data.get('q', ''))
    else:
        query = request.args.get('q', request.args.get('query', ''))

    if not query:
        return jsonify({'error': 'Query required', 'results': [], 'count': 0}), 400

    query_type, params = parse_query(query)
    search_terms = extract_terms(query)
    
    # Build and execute ES query
    if query_type == 'level':
        es_query_body = {"size": 100, "query": {"match_all": {}},
            "_source": ["slug", "identifier", "i18n.en.title", "i18n.af.title", "creators", "dates"]}
    else:
        es_query_body = build_es_query(query)
    
    es_result = es_search([ES_INDEX_IO, ES_INDEX_ACTOR], es_query_body)
    
    if es_result and es_result.get('hits', {}).get('hits'):
        formatted = format_es_results(es_result, search_terms)
        return jsonify({
            'query': query, 'queryType': query_type, 'searchTerms': search_terms,
            'esQuery': es_query_body, **formatted
        })
    
    # Fallback to Fuseki
    print(f"ES no results, Fuseki fallback: {query}")
    terms = search_terms or [query]
    filters = ' || '.join([f'CONTAINS(LCASE(?text), "{t}")' for t in terms])
    sparql = f"""
SELECT DISTINCT ?entity ?title ?type ?identifier WHERE {{
    ?entity a ?type .
    FILTER(?type IN (rico:RecordSet, rico:Record, rico:Person, rico:CorporateBody))
    {{ ?entity rico:title ?title . BIND(?title AS ?text) }}
    UNION {{ ?entity rico:hasAgentName/rico:textualValue ?title . BIND(?title AS ?text) }}
    FILTER({filters})
    OPTIONAL {{ ?entity rico:identifier ?identifier }}
}} ORDER BY ?title LIMIT 100"""
    
    sparql_result = sparql_query(sparql)
    if sparql_result:
        results = []
        for b in sparql_result.get('results', {}).get('bindings', []):
            uri = b.get('entity', b.get('record', {})).get('value', '')
            match = re.search(r'/(\d+)$', uri)
            atom_id = match.group(1) if match else None
            raw_type = b.get('type', {}).get('value', '')
            results.append({
                'uri': uri,
                'atomUrl': f"{ATOM_BASE_URL}/informationobject/{atom_id}" if atom_id else uri,
                'type': raw_type.split('#')[-1] if raw_type else 'Record',
                'title': b.get('title', {}).get('value', 'Untitled'),
                'identifier': b.get('identifier', {}).get('value'),
                'source': 'fuseki'
            })
        return jsonify({
            'query': query, 'queryType': query_type, 'searchTerms': search_terms,
            'sparql': sparql, 'results': results, 'count': len(results), 'source': 'fuseki'
        })
    
    return jsonify({'query': query, 'results': [], 'count': 0, 'message': 'No results'})

@app.route('/api/autocomplete', methods=['GET'])
def api_autocomplete():
    prefix = request.args.get('q', '').strip()
    if len(prefix) < 2:
        return jsonify({'suggestions': []})
    
    es_query_body = {
        "size": 10,
        "query": {
            "bool": {
                "should": [
                    {"match_phrase_prefix": {"i18n.en.title": prefix}},
                    {"match_phrase_prefix": {"i18n.af.title": prefix}},
                    {"match_phrase_prefix": {"i18n.en.authorizedFormOfName": prefix}}
                ]
            }
        },
        "_source": ["slug", "i18n.en.title", "i18n.af.title", "i18n.en.authorizedFormOfName"]
    }
    
    result = es_search([ES_INDEX_IO, ES_INDEX_ACTOR], es_query_body)
    suggestions = []
    seen = set()
    
    if result and 'hits' in result:
        for hit in result['hits'].get('hits', []):
            src = hit.get('_source', {})
            i18n = src.get('i18n', {}).get('en', {})
            text = i18n.get('title') or i18n.get('authorizedFormOfName') or ''
            if text and text not in seen:
                seen.add(text)
                suggestions.append({'text': text, 'slug': src.get('slug')})
    
    return jsonify({'suggestions': suggestions})

@app.route('/api/suggest', methods=['GET'])
def api_suggest():
    return jsonify({'suggestions': [
        {'text': 'all fonds', 'type': 'level'},
        {'text': 'pieterse', 'type': 'fuzzy'},
        {'text': 'van der merwe', 'type': 'fuzzy'},
        {'text': 'engelbrecht', 'type': 'fuzzy'},
    ]})

@app.route('/api/health', methods=['GET'])
def api_health():
    try:
        req = urllib.request.Request(f"{ES_ENDPOINT}/_cluster/health")
        with urllib.request.urlopen(req, timeout=5) as r:
            es_ok = json.loads(r.read()).get('status') in ['green', 'yellow']
    except:
        es_ok = False
    
    fuseki_ok = sparql_query("SELECT (1 as ?t) WHERE {}") is not None
    
    return jsonify({
        'status': 'healthy' if es_ok and fuseki_ok else 'degraded',
        'elasticsearch': 'connected' if es_ok else 'disconnected',
        'fuseki': 'connected' if fuseki_ok else 'disconnected',
        'timestamp': datetime.now().isoformat()
    })

if __name__ == '__main__':
    print("RiC Semantic Search with ES Fuzzy Matching")
    print(f"ES: {ES_ENDPOINT}, Fuseki: {FUSEKI_ENDPOINT}")
    app.run(host='0.0.0.0', port=5001, debug=False)
