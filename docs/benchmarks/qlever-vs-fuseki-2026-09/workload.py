"""RiC SPARQL workload, taken from the queries Heratio actually sends.

Each entry: (id, source in the Heratio code, query). Sample IRIs are real
entities from the 28 Sep 2026 export of the live `ric` dataset.
"""

P = """PREFIX rico: <https://www.ica.org/standards/RiC/ontology#>
PREFIX rdf: <http://www.w3.org/1999/02/22-rdf-syntax-ns#>
PREFIX rdfs: <http://www.w3.org/2000/01/rdf-schema#>
PREFIX skos: <http://www.w3.org/2004/02/skos/core#>
"""

RECORDSET = "https://archives.theahg.co.za/ric/atom-psis/recordset/1108"
INSTANTIATION = "https://archives.theahg.co.za/ric/atom-psis/instantiation/904525"
AGENT = "https://archives.theahg.co.za/ric/atom-psis/corporatebody/630"
PRODUCTION = "https://archives.theahg.co.za/ric/atom-psis/production/902924"
RDFTYPE = "<http://www.w3.org/1999/02/22-rdf-syntax-ns#type>"

QUERIES = [
    ("Q01_search_term", "SparqlQueryService::search", P + """
SELECT DISTINCT ?entity ?type ?label ?description WHERE {
  ?entity a ?type .
  OPTIONAL { ?entity rico:name ?label }
  OPTIONAL { ?entity rico:description ?description }
  OPTIONAL { ?entity skos:prefLabel ?label }
  FILTER(CONTAINS(LCASE(COALESCE(?label, "")), "school") ||
         CONTAINS(LCASE(COALESCE(?description, "")), "school"))
} LIMIT 20"""),

    ("Q02_entity_props", "SparqlQueryService::getEntity", P + f"""
SELECT ?property ?value ?valueType WHERE {{
  <{RECORDSET}> ?property ?value .
  BIND(DATATYPE(?value) AS ?valueType)
}}"""),

    ("Q03_outgoing", "SparqlQueryService::getRelationships (out)", P + f"""
SELECT ?property ?target ?targetType WHERE {{
  <{INSTANTIATION}> ?property ?target .
  OPTIONAL {{ ?target a ?targetType }}
}}"""),

    ("Q04_incoming", "SparqlQueryService::getRelationships (in)", P + f"""
SELECT ?property ?source ?sourceType WHERE {{
  ?source ?property <{RECORDSET}> .
  OPTIONAL {{ ?source a ?sourceType }}
}}"""),

    ("Q05_two_hop_related", "SparqlQueryService::findRelated", P + f"""
SELECT DISTINCT ?related ?relationship ?relatedType WHERE {{
  {{ <{AGENT}> ?prop1 ?intermediate . ?intermediate ?prop2 ?related . BIND(?prop2 AS ?relationship) }}
  UNION
  {{ ?related ?prop1 <{AGENT}> . ?related ?prop2 ?intermediate . BIND(?prop1 AS ?relationship) }}
  FILTER(?related != <{AGENT}>)
  OPTIONAL {{ ?related a ?relatedType }}
}} LIMIT 50"""),

    ("Q06_by_type_page", "SparqlQueryService::getByType", P + """
SELECT ?entity ?name ?description WHERE {
  ?entity a rico:Instantiation .
  OPTIONAL { ?entity rico:name ?name }
  OPTIONAL { ?entity rico:description ?description }
} LIMIT 100 OFFSET 100"""),

    ("Q07_dates", "dates via isOrWasAssociatedWithDate", P + f"""
SELECT ?d ?begin ?end ?expressed WHERE {{
  <{PRODUCTION}> rico:isOrWasAssociatedWithDate ?d .
  OPTIONAL {{ ?d rico:beginningDate ?begin }}
  OPTIONAL {{ ?d rico:endDate ?end }}
  OPTIONAL {{ ?d rico:expressedDate ?expressed }}
}}"""),

    ("Q08_type_statistics", "SparqlQueryService::getStatistics", P + """
SELECT ?type (COUNT(DISTINCT ?entity) AS ?count) WHERE {
  ?entity a ?type .
  FILTER(STRSTARTS(STR(?type), "https://www.ica.org/standards/RiC/"))
} GROUP BY ?type ORDER BY DESC(?count)"""),

    ("Q09_relationship_graph", "RelationshipService graph", P + f"""
SELECT ?s ?p ?o ?sLabel ?oLabel ?sType ?oType WHERE {{
  {{ <{INSTANTIATION}> ?p ?o . BIND(<{INSTANTIATION}> AS ?s)
     FILTER(isURI(?o) && ?p != {RDFTYPE})
     OPTIONAL {{ ?o rico:title ?oLabel }}
     OPTIONAL {{ ?o a ?oType . FILTER(STRSTARTS(STR(?oType), "https://www.ica.org/standards/RiC/ontology#")) }} }}
  UNION
  {{ ?s ?p <{INSTANTIATION}> . BIND(<{INSTANTIATION}> AS ?o)
     FILTER(isURI(?s) && ?p != {RDFTYPE})
     OPTIONAL {{ ?s rico:title ?sLabel }}
     OPTIONAL {{ ?s a ?sType . FILTER(STRSTARTS(STR(?sType), "https://www.ica.org/standards/RiC/ontology#")) }} }}
}}"""),

    ("Q10_overview_recordsets", "RicController::buildOverviewGraph", P + f"""
SELECT ?s ?label ?type ?related ?relLabel ?relType ?pred WHERE {{
  {{ ?s a rico:RecordSet . ?s rico:title ?label . BIND("RecordSet" AS ?type) }}
  OPTIONAL {{
    ?s ?pred ?related .
    FILTER(isURI(?related) && ?pred != {RDFTYPE})
    OPTIONAL {{ ?related rico:title ?relLabel }}
    OPTIONAL {{ ?related a ?relType . FILTER(STRSTARTS(STR(?relType), "https://www.ica.org/standards/RiC/ontology#")) }}
  }}
}} LIMIT 200"""),

    ("Q11_integrity_no_title", "RicController integrity q1", P + """
SELECT (COUNT(?s) AS ?count) WHERE { ?s a rico:RecordSet . FILTER NOT EXISTS { ?s rico:title ?t } }"""),

    ("Q12_integrity_no_creator", "RicController integrity q3", P + """
SELECT (COUNT(?s) AS ?count) WHERE {
  ?s a rico:RecordSet .
  FILTER NOT EXISTS { ?s rico:hasCreator ?c }
  FILTER NOT EXISTS { ?s rico:hasOrHadHolder ?h }
}"""),

    ("Q13_agent_name_search", "FusekiAgentAdapter::buildSparql", P + """
SELECT DISTINCT ?s ?name WHERE {
  ?s a ?agentType .
  VALUES ?agentType { rico:Agent rico:Person rico:CorporateBody rico:Family }
  { ?s rico:name ?name . } UNION
  { ?s rico:hasOrHadName ?nameObj . ?nameObj rico:textualValue ?name . } UNION
  { ?s rdfs:label ?name . } UNION
  { ?s skos:prefLabel ?name . }
  FILTER(CONTAINS(LCASE(STR(?name)), "museum"))
} LIMIT 25"""),

    ("Q14_textual_value_scan", "name search over all textualValue (worst case)", P + """
SELECT ?node ?v WHERE {
  ?node rico:textualValue ?v .
  FILTER(CONTAINS(LCASE(STR(?v)), "photograph"))
} LIMIT 100"""),

    ("Q15_count_by_predicate", "analytics: triples per predicate", """
SELECT ?p (COUNT(*) AS ?n) WHERE { ?s ?p ?o } GROUP BY ?p ORDER BY DESC(?n) LIMIT 30"""),

    ("Q16_count_all", "total triple count", """
SELECT (COUNT(*) AS ?n) WHERE { ?s ?p ?o }"""),

    ("Q17_named_graph_count", "authority-resolution StatusCommand", """
SELECT (COUNT(*) AS ?c) WHERE { GRAPH <https://heratio.theahg.co.za/vocabulary/ric-o> { ?s ?p ?o } }"""),

    ("Q18_extent_join", "analytics: extents with unit and carrier type", P + """
SELECT ?unit (COUNT(*) AS ?n) (SUM(?q) AS ?total) WHERE {
  ?i rico:hasExtent ?e . ?e rico:unitOfMeasurement ?unit ; rico:quantity ?q .
} GROUP BY ?unit ORDER BY DESC(?n) LIMIT 20"""),
]
