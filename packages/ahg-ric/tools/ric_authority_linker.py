#!/usr/bin/env python3
"""
RiC Authority Linker - Version 1.0
==================================

Links AtoM agents to external authority files:
- Wikidata (via SPARQL API)
- VIAF (Virtual International Authority File)
- LCNAF (Library of Congress Name Authority File)

Usage:
    python ric_authority_linker.py --check          # Check linkable agents
    python ric_authority_linker.py --link           # Add owl:sameAs links
    python ric_authority_linker.py --agent-id 123   # Link specific agent
    python ric_authority_linker.py --report         # Generate linking report
"""

import json
import os
import sys
import argparse
import time
import re
from typing import Dict, List, Optional, Tuple
from urllib.parse import quote_plus
import urllib.request
import urllib.error

# Fuseki configuration
FUSEKI_ENDPOINT = os.environ.get('FUSEKI_ENDPOINT', 'http://localhost:3030/ric')
FUSEKI_QUERY = f"{FUSEKI_ENDPOINT}/query"
FUSEKI_UPDATE = f"{FUSEKI_ENDPOINT}/update"

# Rate limiting
WIKIDATA_DELAY = 1.0  # seconds between Wikidata requests
VIAF_DELAY = 0.5
LCNAF_DELAY = 0.5


class AuthorityLinker:
    """Links RiC agents to external authority files."""
    
    def __init__(self):
        self.stats = {
            'agents_checked': 0,
            'wikidata_matches': 0,
            'viaf_matches': 0,
            'lcnaf_matches': 0,
            'links_added': 0,
            'errors': 0
        }
        self.matches = []
    
    def sparql_query(self, query: str) -> Optional[Dict]:
        """Execute SPARQL query against Fuseki."""
        try:
            data = query.encode('utf-8')
            req = urllib.request.Request(
                FUSEKI_QUERY,
                data=data,
                headers={
                    'Content-Type': 'application/sparql-query',
                    'Accept': 'application/json'
                }
            )
            with urllib.request.urlopen(req, timeout=30) as response:
                return json.loads(response.read().decode('utf-8'))
        except Exception as e:
            print(f"SPARQL query error: {e}")
            return None
    
    def sparql_update(self, update: str) -> bool:
        """Execute SPARQL update against Fuseki."""
        try:
            data = update.encode('utf-8')
            req = urllib.request.Request(
                FUSEKI_UPDATE,
                data=data,
                headers={
                    'Content-Type': 'application/sparql-update'
                }
            )
            with urllib.request.urlopen(req, timeout=30) as response:
                return response.status == 200 or response.status == 204
        except Exception as e:
            print(f"SPARQL update error: {e}")
            return False
    
    def get_agents(self) -> List[Dict]:
        """Get all agents from triplestore."""
        query = """
PREFIX rico: <https://www.ica.org/standards/RiC/ontology#>
PREFIX owl: <http://www.w3.org/2002/07/owl#>

SELECT ?agent ?name ?type ?dates WHERE {
    ?agent a ?type .
    FILTER(?type IN (rico:Person, rico:CorporateBody, rico:Family))
    
    ?agent rico:hasAgentName/rico:textualValue ?name .
    
    OPTIONAL { ?agent rico:hasBeginningDate ?dates }
    
    # Exclude agents that already have sameAs links
    FILTER NOT EXISTS { ?agent owl:sameAs ?external }
}
ORDER BY ?name
        """
        result = self.sparql_query(query)
        
        if not result or 'results' not in result:
            return []
        
        agents = []
        for row in result['results']['bindings']:
            agents.append({
                'uri': row['agent']['value'],
                'name': row['name']['value'],
                'type': row['type']['value'].split('#')[-1],
                'dates': row.get('dates', {}).get('value')
            })
        
        return agents
    
    def search_wikidata(self, name: str, agent_type: str, dates: Optional[str] = None) -> Optional[Dict]:
        """Search Wikidata for matching entity."""
        # Wikidata entity types
        type_filter = ""
        if agent_type == 'Person':
            type_filter = "?item wdt:P31 wd:Q5 ."  # instance of human
        elif agent_type == 'CorporateBody':
            type_filter = """
                ?item wdt:P31 ?orgType .
                FILTER(?orgType IN (wd:Q43229, wd:Q4830453, wd:Q327333, wd:Q6881511))
            """  # organization, business, gov agency, museum
        
        # Clean name for search
        search_name = re.sub(r'\s+', ' ', name).strip()
        search_name = search_name.replace('"', '\\"')
        
        query = f"""
SELECT ?item ?itemLabel ?itemDescription ?viaf WHERE {{
    SERVICE wikibase:mwapi {{
        bd:serviceParam wikibase:api "EntitySearch" ;
                        wikibase:endpoint "www.wikidata.org" ;
                        mwapi:search "{search_name}" ;
                        mwapi:language "en" .
        ?item wikibase:apiOutputItem mwapi:item .
    }}
    
    {type_filter}
    
    OPTIONAL {{ ?item wdt:P214 ?viaf }}
    
    SERVICE wikibase:label {{ bd:serviceParam wikibase:language "en" . }}
}}
LIMIT 5
        """
        
        try:
            url = "https://query.wikidata.org/sparql"
            data = f"query={quote_plus(query)}".encode('utf-8')
            req = urllib.request.Request(
                url,
                data=data,
                headers={
                    'Content-Type': 'application/x-www-form-urlencoded',
                    'Accept': 'application/json',
                    'User-Agent': 'RiC-Authority-Linker/1.0 (archives@theahg.co.za)'
                }
            )
            
            with urllib.request.urlopen(req, timeout=30) as response:
                result = json.loads(response.read().decode('utf-8'))
                
                if result.get('results', {}).get('bindings'):
                    best = result['results']['bindings'][0]
                    return {
                        'source': 'wikidata',
                        'uri': best['item']['value'],
                        'label': best.get('itemLabel', {}).get('value', ''),
                        'description': best.get('itemDescription', {}).get('value', ''),
                        'viaf': best.get('viaf', {}).get('value')
                    }
        except Exception as e:
            print(f"  Wikidata search error: {e}")
            self.stats['errors'] += 1
        
        return None
    
    def search_viaf(self, name: str, agent_type: str) -> Optional[Dict]:
        """Search VIAF for matching entity."""
        # VIAF CQL query
        cql_type = "local.personalNames"
        if agent_type == 'CorporateBody':
            cql_type = "local.corporateNames"
        elif agent_type == 'Family':
            cql_type = "local.personalNames"
        
        search_name = quote_plus(name)
        url = f"https://viaf.org/viaf/search?query={cql_type}+all+%22{search_name}%22&sortKeys=holdingscount&maximumRecords=5&httpAccept=application/json"
        
        try:
            req = urllib.request.Request(
                url,
                headers={
                    'Accept': 'application/json',
                    'User-Agent': 'RiC-Authority-Linker/1.0'
                }
            )
            
            with urllib.request.urlopen(req, timeout=30) as response:
                result = json.loads(response.read().decode('utf-8'))
                
                records = result.get('searchRetrieveResponse', {}).get('records', [])
                if records:
                    record = records[0].get('record', {}).get('recordData', {})
                    viaf_id = record.get('viafID')
                    if viaf_id:
                        # Get main heading
                        main_heading = ''
                        headings = record.get('mainHeadings', {}).get('data', [])
                        if isinstance(headings, list) and headings:
                            main_heading = headings[0].get('text', '')
                        elif isinstance(headings, dict):
                            main_heading = headings.get('text', '')
                        
                        return {
                            'source': 'viaf',
                            'uri': f"https://viaf.org/viaf/{viaf_id}",
                            'viaf_id': viaf_id,
                            'label': main_heading
                        }
        except Exception as e:
            print(f"  VIAF search error: {e}")
            self.stats['errors'] += 1
        
        return None
    
    def search_lcnaf(self, name: str, agent_type: str) -> Optional[Dict]:
        """Search Library of Congress Name Authority File."""
        search_name = quote_plus(name)
        
        # LC Linked Data Service
        url = f"https://id.loc.gov/authorities/names/suggest2?q={search_name}&count=5"
        
        try:
            req = urllib.request.Request(
                url,
                headers={
                    'Accept': 'application/json',
                    'User-Agent': 'RiC-Authority-Linker/1.0'
                }
            )
            
            with urllib.request.urlopen(req, timeout=30) as response:
                result = json.loads(response.read().decode('utf-8'))
                
                hits = result.get('hits', [])
                if hits:
                    best = hits[0]
                    return {
                        'source': 'lcnaf',
                        'uri': best.get('uri', ''),
                        'label': best.get('aLabel', ''),
                        'lccn': best.get('token', '')
                    }
        except Exception as e:
            print(f"  LCNAF search error: {e}")
            self.stats['errors'] += 1
        
        return None
    
    def add_sameas_link(self, agent_uri: str, external_uri: str, source: str) -> bool:
        """Add owl:sameAs triple to triplestore."""
        update = f"""
PREFIX owl: <http://www.w3.org/2002/07/owl#>
PREFIX rdfs: <http://www.w3.org/2000/01/rdf-schema#>

INSERT DATA {{
    <{agent_uri}> owl:sameAs <{external_uri}> .
    <{external_uri}> rdfs:label "{source} authority record" .
}}
        """
        
        success = self.sparql_update(update)
        if success:
            self.stats['links_added'] += 1
        return success
    
    def check_agents(self, limit: int = 50) -> List[Dict]:
        """Check which agents can be linked."""
        print(f"\n{'='*60}")
        print("RiC Authority Linker - Checking Agents")
        print(f"{'='*60}\n")
        
        agents = self.get_agents()
        print(f"Found {len(agents)} agents without external links\n")
        
        if limit:
            agents = agents[:limit]
        
        for agent in agents:
            self.stats['agents_checked'] += 1
            print(f"[{self.stats['agents_checked']}] {agent['name']} ({agent['type']})")
            
            matches = {'agent': agent, 'links': []}
            
            # Search Wikidata
            time.sleep(WIKIDATA_DELAY)
            wd_match = self.search_wikidata(agent['name'], agent['type'], agent.get('dates'))
            if wd_match:
                print(f"  ✓ Wikidata: {wd_match['uri']}")
                print(f"    → {wd_match.get('description', 'No description')}")
                matches['links'].append(wd_match)
                self.stats['wikidata_matches'] += 1
            
            # Search VIAF
            time.sleep(VIAF_DELAY)
            viaf_match = self.search_viaf(agent['name'], agent['type'])
            if viaf_match:
                print(f"  ✓ VIAF: {viaf_match['uri']}")
                matches['links'].append(viaf_match)
                self.stats['viaf_matches'] += 1
            
            # Search LCNAF
            time.sleep(LCNAF_DELAY)
            lcnaf_match = self.search_lcnaf(agent['name'], agent['type'])
            if lcnaf_match:
                print(f"  ✓ LCNAF: {lcnaf_match['uri']}")
                matches['links'].append(lcnaf_match)
                self.stats['lcnaf_matches'] += 1
            
            if matches['links']:
                self.matches.append(matches)
            else:
                print(f"  ✗ No matches found")
            
            print()
        
        return self.matches
    
    def link_agents(self, dry_run: bool = False) -> None:
        """Add owl:sameAs links for matching agents."""
        print(f"\n{'='*60}")
        print(f"RiC Authority Linker - {'DRY RUN' if dry_run else 'Adding Links'}")
        print(f"{'='*60}\n")
        
        if not self.matches:
            self.check_agents()
        
        for match in self.matches:
            agent = match['agent']
            print(f"\n{agent['name']} ({agent['type']})")
            
            for link in match['links']:
                if dry_run:
                    print(f"  [DRY RUN] Would add: owl:sameAs <{link['uri']}>")
                else:
                    success = self.add_sameas_link(agent['uri'], link['uri'], link['source'])
                    status = "✓ Added" if success else "✗ Failed"
                    print(f"  {status}: owl:sameAs <{link['uri']}>")
    
    def link_single_agent(self, agent_uri: str) -> None:
        """Link a specific agent by URI or ID."""
        # If just an ID, construct the URI
        if agent_uri.isdigit():
            # Query to find the agent type
            query = f"""
PREFIX rico: <https://www.ica.org/standards/RiC/ontology#>

SELECT ?agent ?name ?type WHERE {{
    ?agent rico:hasAgentName/rico:textualValue ?name .
    ?agent a ?type .
    FILTER(CONTAINS(STR(?agent), "/{agent_uri}"))
    FILTER(?type IN (rico:Person, rico:CorporateBody, rico:Family))
}}
LIMIT 1
            """
            result = self.sparql_query(query)
            if result and result.get('results', {}).get('bindings'):
                row = result['results']['bindings'][0]
                agent = {
                    'uri': row['agent']['value'],
                    'name': row['name']['value'],
                    'type': row['type']['value'].split('#')[-1]
                }
            else:
                print(f"Agent {agent_uri} not found in triplestore")
                return
        else:
            agent = {'uri': agent_uri, 'name': '', 'type': 'Person'}
        
        print(f"\nLinking agent: {agent['name']} ({agent['uri']})")
        
        # Search all authorities
        wd = self.search_wikidata(agent['name'], agent['type'])
        if wd:
            self.add_sameas_link(agent['uri'], wd['uri'], 'wikidata')
            print(f"  ✓ Wikidata: {wd['uri']}")
        
        time.sleep(VIAF_DELAY)
        viaf = self.search_viaf(agent['name'], agent['type'])
        if viaf:
            self.add_sameas_link(agent['uri'], viaf['uri'], 'viaf')
            print(f"  ✓ VIAF: {viaf['uri']}")
        
        time.sleep(LCNAF_DELAY)
        lcnaf = self.search_lcnaf(agent['name'], agent['type'])
        if lcnaf:
            self.add_sameas_link(agent['uri'], lcnaf['uri'], 'lcnaf')
            print(f"  ✓ LCNAF: {lcnaf['uri']}")
    
    def generate_report(self) -> str:
        """Generate linking statistics report."""
        report = f"""
{'='*60}
RiC AUTHORITY LINKING REPORT
{'='*60}

Agents Checked:     {self.stats['agents_checked']}

Matches Found:
  Wikidata:         {self.stats['wikidata_matches']}
  VIAF:             {self.stats['viaf_matches']}
  LCNAF:            {self.stats['lcnaf_matches']}

Links Added:        {self.stats['links_added']}
Errors:             {self.stats['errors']}

Match Rate:         {self._match_rate()}%
{'='*60}

MATCHED AGENTS:
"""
        for match in self.matches:
            agent = match['agent']
            report += f"\n• {agent['name']} ({agent['type']})\n"
            for link in match['links']:
                report += f"  → {link['source']}: {link['uri']}\n"
        
        return report
    
    def _match_rate(self) -> float:
        if self.stats['agents_checked'] == 0:
            return 0.0
        matched = len(self.matches)
        return round(matched / self.stats['agents_checked'] * 100, 1)


def main():
    parser = argparse.ArgumentParser(description='RiC Authority Linker')
    parser.add_argument('--check', action='store_true', help='Check linkable agents')
    parser.add_argument('--link', action='store_true', help='Add owl:sameAs links')
    parser.add_argument('--dry-run', action='store_true', help='Show what would be linked')
    parser.add_argument('--agent-id', type=str, help='Link specific agent by ID')
    parser.add_argument('--limit', type=int, default=50, help='Max agents to check')
    parser.add_argument('--report', action='store_true', help='Generate report')
    parser.add_argument('--output', '-o', type=str, help='Output report file')
    
    args = parser.parse_args()
    
    linker = AuthorityLinker()
    
    if args.agent_id:
        linker.link_single_agent(args.agent_id)
    elif args.check or args.link:
        linker.check_agents(limit=args.limit)
        
        if args.link:
            linker.link_agents(dry_run=args.dry_run)
        
        if args.report or args.output:
            report = linker.generate_report()
            print(report)
            
            if args.output:
                with open(args.output, 'w') as f:
                    f.write(report)
                print(f"\nReport saved to: {args.output}")
    else:
        parser.print_help()
        print("\n\nExamples:")
        print("  python ric_authority_linker.py --check --limit 10")
        print("  python ric_authority_linker.py --link --dry-run")
        print("  python ric_authority_linker.py --link --report -o linking_report.txt")
        print("  python ric_authority_linker.py --agent-id 900140")


if __name__ == '__main__':
    main()
