#!/usr/bin/env python3
"""
RiC SHACL Validator - Version 1.0
=================================

Validates RiC-O data in Fuseki triplestore against SHACL shapes.

Requirements:
    pip install pyshacl rdflib

Usage:
    python ric_shacl_validator.py --validate           # Validate all data
    python ric_shacl_validator.py --validate --fonds 776  # Validate specific fonds
    python ric_shacl_validator.py --report             # Generate HTML report
    python ric_shacl_validator.py --summary            # Quick summary
"""

import os
import sys
import json
import argparse
from datetime import datetime
from typing import Dict, List, Optional, Tuple
import urllib.request

try:
    from pyshacl import validate
    from rdflib import Graph, Namespace, URIRef
    from rdflib.namespace import RDF, RDFS, OWL, XSD
except ImportError:
    print("Error: Required packages not found. Install with:")
    print("  pip install pyshacl rdflib --break-system-packages")
    sys.exit(1)

# Configuration
FUSEKI_ENDPOINT = os.environ.get('FUSEKI_ENDPOINT', 'http://localhost:3030/ric')
SHAPES_FILE = os.environ.get('SHACL_SHAPES', '/usr/share/nginx/archive/ric_shacl_shapes.ttl')

# Namespaces
RICO = Namespace('https://www.ica.org/standards/RiC/ontology#')
SPECTRUM = Namespace('https://collectionstrust.org.uk/spectrum#')
GRAP = Namespace('https://www.asb.co.za/grap#')
SH = Namespace('http://www.w3.org/ns/shacl#')


class RiCSHACLValidator:
    """Validates RiC-O data against SHACL shapes."""
    
    def __init__(self, shapes_file: str = SHAPES_FILE):
        self.shapes_file = shapes_file
        self.shapes_graph = None
        self.data_graph = None
        self.results = None
        self.stats = {
            'total_violations': 0,
            'by_severity': {'Violation': 0, 'Warning': 0, 'Info': 0},
            'by_shape': {},
            'by_entity_type': {}
        }
    
    def load_shapes(self) -> bool:
        """Load SHACL shapes from file."""
        try:
            self.shapes_graph = Graph()
            self.shapes_graph.parse(self.shapes_file, format='turtle')
            print(f"Loaded {len(self.shapes_graph)} triples from shapes file")
            return True
        except Exception as e:
            print(f"Error loading shapes: {e}")
            return False
    
    def fetch_data_from_fuseki(self, graph_uri: Optional[str] = None) -> bool:
        """Fetch RDF data from Fuseki triplestore."""
        query = """
CONSTRUCT { ?s ?p ?o }
WHERE { ?s ?p ?o }
        """
        
        if graph_uri:
            query = f"""
CONSTRUCT {{ ?s ?p ?o }}
WHERE {{
    GRAPH <{graph_uri}> {{ ?s ?p ?o }}
}}
            """
        
        try:
            url = f"{FUSEKI_ENDPOINT}/query"
            data = f"query={urllib.parse.quote(query)}".encode('utf-8')
            req = urllib.request.Request(
                url,
                data=data,
                headers={
                    'Content-Type': 'application/x-www-form-urlencoded',
                    'Accept': 'application/n-triples'
                }
            )
            
            with urllib.request.urlopen(req, timeout=60) as response:
                content = response.read().decode('utf-8')
                
                self.data_graph = Graph()
                self.data_graph.parse(data=content, format='nt')
                print(f"Fetched {len(self.data_graph)} triples from Fuseki")
                return True
                
        except Exception as e:
            print(f"Error fetching data: {e}")
            return False
    
    def load_data_from_file(self, filepath: str) -> bool:
        """Load RDF data from a file."""
        try:
            self.data_graph = Graph()
            
            if filepath.endswith('.jsonld'):
                self.data_graph.parse(filepath, format='json-ld')
            elif filepath.endswith('.ttl'):
                self.data_graph.parse(filepath, format='turtle')
            elif filepath.endswith('.nt'):
                self.data_graph.parse(filepath, format='nt')
            else:
                self.data_graph.parse(filepath)
            
            print(f"Loaded {len(self.data_graph)} triples from {filepath}")
            return True
        except Exception as e:
            print(f"Error loading data: {e}")
            return False
    
    def validate(self) -> Tuple[bool, Graph, str]:
        """Run SHACL validation."""
        if not self.shapes_graph:
            if not self.load_shapes():
                return False, None, "Failed to load shapes"
        
        if not self.data_graph:
            if not self.fetch_data_from_fuseki():
                return False, None, "Failed to fetch data"
        
        print("\nRunning SHACL validation...")
        
        conforms, results_graph, results_text = validate(
            self.data_graph,
            shacl_graph=self.shapes_graph,
            inference='rdfs',
            abort_on_first=False,
            meta_shacl=False,
            advanced=True,
            js=False,
            debug=False
        )
        
        self.results = results_graph
        self._analyze_results(results_graph)
        
        return conforms, results_graph, results_text
    
    def _analyze_results(self, results_graph: Graph):
        """Analyze validation results for statistics."""
        for result in results_graph.subjects(RDF.type, SH.ValidationResult):
            self.stats['total_violations'] += 1
            
            # Severity
            severity = results_graph.value(result, SH.resultSeverity)
            if severity:
                sev_name = str(severity).split('#')[-1]
                self.stats['by_severity'][sev_name] = self.stats['by_severity'].get(sev_name, 0) + 1
            
            # Source shape
            source_shape = results_graph.value(result, SH.sourceShape)
            if source_shape:
                shape_name = str(source_shape).split('#')[-1]
                self.stats['by_shape'][shape_name] = self.stats['by_shape'].get(shape_name, 0) + 1
            
            # Focus node (entity)
            focus_node = results_graph.value(result, SH.focusNode)
            if focus_node:
                # Try to get the type
                entity_type = self.data_graph.value(focus_node, RDF.type)
                if entity_type:
                    type_name = str(entity_type).split('#')[-1]
                    self.stats['by_entity_type'][type_name] = self.stats['by_entity_type'].get(type_name, 0) + 1
    
    def get_violations(self) -> List[Dict]:
        """Extract detailed violation information."""
        violations = []
        
        if not self.results:
            return violations
        
        for result in self.results.subjects(RDF.type, SH.ValidationResult):
            violation = {
                'focus_node': str(self.results.value(result, SH.focusNode) or ''),
                'path': str(self.results.value(result, SH.resultPath) or ''),
                'message': str(self.results.value(result, SH.resultMessage) or ''),
                'severity': str(self.results.value(result, SH.resultSeverity) or '').split('#')[-1],
                'source_shape': str(self.results.value(result, SH.sourceShape) or '').split('#')[-1],
                'value': str(self.results.value(result, SH.value) or '')
            }
            violations.append(violation)
        
        return violations
    
    def print_summary(self):
        """Print validation summary."""
        print(f"\n{'='*60}")
        print("RiC-O SHACL VALIDATION SUMMARY")
        print(f"{'='*60}")
        print(f"\nTotal Issues: {self.stats['total_violations']}")
        
        print("\nBy Severity:")
        for sev, count in sorted(self.stats['by_severity'].items()):
            icon = {'Violation': '❌', 'Warning': '⚠️', 'Info': 'ℹ️'}.get(sev, '•')
            print(f"  {icon} {sev}: {count}")
        
        if self.stats['by_shape']:
            print("\nBy Validation Rule:")
            for shape, count in sorted(self.stats['by_shape'].items(), key=lambda x: -x[1])[:10]:
                print(f"  • {shape}: {count}")
        
        if self.stats['by_entity_type']:
            print("\nBy Entity Type:")
            for etype, count in sorted(self.stats['by_entity_type'].items(), key=lambda x: -x[1]):
                print(f"  • {etype}: {count}")
        
        print(f"\n{'='*60}")
    
    def generate_html_report(self, output_file: str = 'validation_report.html'):
        """Generate HTML validation report."""
        violations = self.get_violations()
        
        # Group by severity
        by_severity = {'Violation': [], 'Warning': [], 'Info': []}
        for v in violations:
            sev = v['severity'] if v['severity'] in by_severity else 'Info'
            by_severity[sev].append(v)
        
        html = f"""<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>RiC-O Validation Report</title>
    <style>
        body {{ font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; margin: 0; padding: 20px; background: #f5f5f5; }}
        .container {{ max-width: 1200px; margin: 0 auto; }}
        h1 {{ color: #1a5276; }}
        .summary {{ background: white; padding: 20px; border-radius: 8px; margin-bottom: 20px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); }}
        .stat {{ display: inline-block; margin-right: 30px; }}
        .stat-value {{ font-size: 2em; font-weight: bold; }}
        .stat-label {{ color: #666; }}
        .violation {{ color: #c0392b; }}
        .warning {{ color: #d68910; }}
        .info {{ color: #2874a6; }}
        .section {{ background: white; padding: 20px; border-radius: 8px; margin-bottom: 20px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); }}
        table {{ width: 100%; border-collapse: collapse; }}
        th, td {{ padding: 10px; text-align: left; border-bottom: 1px solid #eee; }}
        th {{ background: #f8f9fa; font-weight: 600; }}
        .entity-link {{ color: #2874a6; text-decoration: none; }}
        .entity-link:hover {{ text-decoration: underline; }}
        .badge {{ display: inline-block; padding: 3px 8px; border-radius: 4px; font-size: 12px; font-weight: 500; }}
        .badge-violation {{ background: #fadbd8; color: #922b21; }}
        .badge-warning {{ background: #fdebd0; color: #9c640c; }}
        .badge-info {{ background: #d4e6f1; color: #1a5276; }}
    </style>
</head>
<body>
    <div class="container">
        <h1>🔍 RiC-O Validation Report</h1>
        <p>Generated: {datetime.now().strftime('%Y-%m-%d %H:%M:%S')}</p>
        
        <div class="summary">
            <div class="stat">
                <div class="stat-value">{len(self.data_graph) if self.data_graph else 0}</div>
                <div class="stat-label">Total Triples</div>
            </div>
            <div class="stat">
                <div class="stat-value violation">{self.stats['by_severity'].get('Violation', 0)}</div>
                <div class="stat-label">Violations</div>
            </div>
            <div class="stat">
                <div class="stat-value warning">{self.stats['by_severity'].get('Warning', 0)}</div>
                <div class="stat-label">Warnings</div>
            </div>
            <div class="stat">
                <div class="stat-value info">{self.stats['by_severity'].get('Info', 0)}</div>
                <div class="stat-label">Info</div>
            </div>
        </div>
"""
        
        # Violations section
        if by_severity['Violation']:
            html += """
        <div class="section">
            <h2>❌ Violations (Must Fix)</h2>
            <table>
                <thead>
                    <tr><th>Entity</th><th>Rule</th><th>Message</th></tr>
                </thead>
                <tbody>
"""
            for v in by_severity['Violation'][:50]:
                entity_id = v['focus_node'].split('/')[-1]
                html += f"""
                    <tr>
                        <td><code>{entity_id}</code></td>
                        <td><span class="badge badge-violation">{v['source_shape']}</span></td>
                        <td>{v['message']}</td>
                    </tr>
"""
            html += """
                </tbody>
            </table>
        </div>
"""
        
        # Warnings section
        if by_severity['Warning']:
            html += """
        <div class="section">
            <h2>⚠️ Warnings (Should Fix)</h2>
            <table>
                <thead>
                    <tr><th>Entity</th><th>Rule</th><th>Message</th></tr>
                </thead>
                <tbody>
"""
            for v in by_severity['Warning'][:50]:
                entity_id = v['focus_node'].split('/')[-1]
                html += f"""
                    <tr>
                        <td><code>{entity_id}</code></td>
                        <td><span class="badge badge-warning">{v['source_shape']}</span></td>
                        <td>{v['message']}</td>
                    </tr>
"""
            html += """
                </tbody>
            </table>
        </div>
"""
        
        # By Shape Statistics
        if self.stats['by_shape']:
            html += """
        <div class="section">
            <h2>📊 Issues by Validation Rule</h2>
            <table>
                <thead>
                    <tr><th>Rule</th><th>Count</th></tr>
                </thead>
                <tbody>
"""
            for shape, count in sorted(self.stats['by_shape'].items(), key=lambda x: -x[1]):
                html += f"""
                    <tr><td>{shape}</td><td>{count}</td></tr>
"""
            html += """
                </tbody>
            </table>
        </div>
"""
        
        html += """
    </div>
</body>
</html>
"""
        
        with open(output_file, 'w') as f:
            f.write(html)
        
        print(f"\nHTML report saved to: {output_file}")
        return output_file
    
    def generate_json_report(self, output_file: str = 'validation_report.json'):
        """Generate JSON validation report."""
        report = {
            'generated': datetime.now().isoformat(),
            'data_triples': len(self.data_graph) if self.data_graph else 0,
            'statistics': self.stats,
            'violations': self.get_violations()
        }
        
        with open(output_file, 'w') as f:
            json.dump(report, f, indent=2)
        
        print(f"JSON report saved to: {output_file}")
        return output_file


def main():
    parser = argparse.ArgumentParser(description='RiC SHACL Validator')
    parser.add_argument('--validate', action='store_true', help='Run validation')
    parser.add_argument('--file', '-f', type=str, help='Validate specific file instead of Fuseki')
    parser.add_argument('--shapes', '-s', type=str, default=SHAPES_FILE, help='SHACL shapes file')
    parser.add_argument('--summary', action='store_true', help='Print summary only')
    parser.add_argument('--report', action='store_true', help='Generate HTML report')
    parser.add_argument('--json', action='store_true', help='Generate JSON report')
    parser.add_argument('--output', '-o', type=str, help='Output file path')
    parser.add_argument('--verbose', '-v', action='store_true', help='Show all violations')
    
    args = parser.parse_args()
    
    if not (args.validate or args.summary or args.report or args.json):
        parser.print_help()
        print("\n\nExamples:")
        print("  python ric_shacl_validator.py --validate --summary")
        print("  python ric_shacl_validator.py --validate --report -o report.html")
        print("  python ric_shacl_validator.py --file output.jsonld --validate")
        print("  python ric_shacl_validator.py --validate --json -o report.json")
        return
    
    validator = RiCSHACLValidator(shapes_file=args.shapes)
    
    # Load data
    if args.file:
        if not validator.load_data_from_file(args.file):
            sys.exit(1)
    
    # Run validation
    conforms, results, text = validator.validate()
    
    print(f"\n✓ Data {'CONFORMS' if conforms else 'DOES NOT CONFORM'} to RiC-O shapes")
    
    # Output options
    if args.summary or args.validate:
        validator.print_summary()
    
    if args.verbose:
        print("\n" + text)
    
    if args.report:
        output = args.output or 'validation_report.html'
        validator.generate_html_report(output)
    
    if args.json:
        output = args.output or 'validation_report.json'
        validator.generate_json_report(output)
    
    # Exit code based on conformance
    sys.exit(0 if conforms else 1)


if __name__ == '__main__':
    main()
