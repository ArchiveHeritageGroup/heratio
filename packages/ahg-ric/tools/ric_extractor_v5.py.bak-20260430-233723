#!/usr/bin/env python3
"""
AtoM to Records in Context (RiC) Extraction - Version 5
========================================================

Phase 5 Implementation - Spectrum & GRAP Extensions (~95% Coverage)

New Entities/Extensions:
- Condition Assessments (spectrum_condition_check)
- Valuations (spectrum_valuation)
- Loans Out (spectrum_loan_out)
- Movements (spectrum_movement)
- GRAP Heritage Assets (grap_heritage_asset, spectrum_grap_data)

Usage:
    python ric_extractor_v5.py --list-fonds
    python ric_extractor_v5.py --fonds-id 123 --output output.jsonld --pretty
"""

import json
import os
import sys
import argparse
from datetime import datetime
from typing import Dict, List, Optional, Any
from collections import defaultdict
from decimal import Decimal

try:
    import mysql.connector
    from mysql.connector import Error
except ImportError:
    print("Error: mysql-connector-python required. Install with:")
    print("  pip install mysql-connector-python")
    sys.exit(1)


class DecimalEncoder(json.JSONEncoder):
    """Handle Decimal serialization."""
    def default(self, obj):
        if isinstance(obj, Decimal):
            return float(obj)
        return super().default(obj)


class RiCExtractor:
    """Extracts AtoM data and transforms to RiC-O JSON-LD with Spectrum/GRAP extensions."""
    
    LEVEL_TO_RIC = {
        'fonds': 'RecordSet',
        'subfonds': 'RecordSet',
        'collection': 'RecordSet',
        'series': 'RecordSet',
        'subseries': 'RecordSet',
        'file': 'RecordSet',
        'item': 'Record',
        'part': 'RecordPart',
    }
    
    ACTOR_TYPE_TO_RIC = {
        'corporate body': 'CorporateBody',
        'person': 'Person',
        'family': 'Family',
    }
    
    EVENT_TYPE_TO_RIC = {
        'creation': 'Production',
        'accumulation': 'Accumulation',
        'contribution': 'Production',
        'collection': 'Accumulation',
        'custody': 'Activity',
        'publication': 'Activity',
        'reproduction': 'Activity',
    }
    
    def __init__(self, db_config: Dict[str, str], base_uri: str, instance_id: str):
        self.db_config = db_config
        self.base_uri = base_uri.rstrip('/')
        self.instance_id = instance_id
        self.connection = None
        self.cursor = None
        
        # Entity caches
        self.records = {}
        self.agents = {}
        self.activities = {}
        self.places = {}
        self.subjects = {}
        self.genres = {}
        self.instantiations = {}
        self.rules = {}
        self.mandates = {}
        self.functions = {}
        self.relations = []
        self.repository = None
        
        # Phase 5: Spectrum/GRAP entities
        self.condition_checks = {}
        self.valuations = {}
        self.loans_out = {}
        self.movements = {}
        self.grap_assets = {}
        
        self.record_order = {}
        self._taxonomy_cache = {}
        
    def connect(self):
        try:
            self.connection = mysql.connector.connect(**self.db_config)
            self.cursor = self.connection.cursor(dictionary=True)
            print(f"Connected to database: {self.db_config['database']}")
            self._cache_taxonomy_ids()
        except Error as e:
            print(f"Database connection error: {e}")
            sys.exit(1)
    
    def _cache_taxonomy_ids(self):
        query = """
            SELECT t.id, ti.name
            FROM taxonomy t
            JOIN taxonomy_i18n ti ON t.id = ti.id AND ti.culture = 'en'
        """
        self.cursor.execute(query)
        for row in self.cursor.fetchall():
            name = (row['name'] or '').lower()
            if 'subject' in name:
                self._taxonomy_cache['subject'] = row['id']
            elif 'place' in name:
                self._taxonomy_cache['place'] = row['id']
            elif 'genre' in name:
                self._taxonomy_cache['genre'] = row['id']
                
    def close(self):
        if self.cursor:
            self.cursor.close()
        if self.connection:
            self.connection.close()
            
    def mint_uri(self, entity_type: str, entity_id) -> str:
        return f"{self.base_uri}/{self.instance_id}/{entity_type.lower()}/{entity_id}"
    
    def list_fonds(self) -> List[Dict]:
        query = """
            SELECT 
                io.id, io.identifier, ioi.title,
                (SELECT COUNT(*) FROM information_object d 
                 WHERE d.lft > io.lft AND d.rgt < io.rgt) as descendant_count
            FROM information_object io
            JOIN information_object_i18n ioi ON io.id = ioi.id AND ioi.culture = 'en'
            JOIN term_i18n ti ON io.level_of_description_id = ti.id AND ti.culture = 'en'
            WHERE io.parent_id = 1 AND LOWER(ti.name) = 'fonds'
            ORDER BY ioi.title
        """
        self.cursor.execute(query)
        return self.cursor.fetchall()

    def list_standalone(self) -> List[Dict]:
        """List top-level records that are not fonds (standalone records)"""
        query = """
            SELECT
                io.id, io.identifier, ioi.title,
                COALESCE(ti.name, 'No level') as level,
                (SELECT COUNT(*) FROM information_object d
                 WHERE d.lft > io.lft AND d.rgt < io.rgt) as descendant_count
            FROM information_object io
            LEFT JOIN information_object_i18n ioi ON io.id = ioi.id AND ioi.culture = 'en'
            LEFT JOIN term_i18n ti ON io.level_of_description_id = ti.id AND ti.culture = 'en'
            WHERE io.parent_id = 1 AND io.id > 1
            AND (ti.name IS NULL OR LOWER(ti.name) != 'fonds')
            ORDER BY ioi.title
        """
        self.cursor.execute(query)
        return self.cursor.fetchall()
    
    def extract_fonds(self, fonds_id: int) -> Dict:
        self.cursor.execute("SELECT id FROM information_object WHERE id = %s", (fonds_id,))
        if not self.cursor.fetchone():
            raise ValueError(f"Fonds with ID {fonds_id} not found")
        
        # Clear all caches
        self.records = {}
        self.agents = {}
        self.activities = {}
        self.places = {}
        self.subjects = {}
        self.genres = {}
        self.instantiations = {}
        self.rules = {}
        self.mandates = {}
        self.functions = {}
        self.relations = []
        self.record_order = {}
        self.repository = None
        self.condition_checks = {}
        self.valuations = {}
        self.loans_out = {}
        self.movements = {}
        self.grap_assets = {}
        
        # Core extraction (Phases 1-4)
        self._extract_records_by_parent(fonds_id)
        self._extract_agents_by_records()
        self._extract_activities_by_records()
        self._extract_access_points()
        self._extract_digital_objects()
        self._extract_related_materials()
        self._extract_rights_and_rules()
        self._extract_functions()
        self._extract_mandates_from_agents()
        self._extract_repository(fonds_id)
        self._build_creator_shortcuts()
        self._build_temporal_relations()
        self._build_equivalence_candidates()
        
        # Phase 5: Spectrum/GRAP extraction
        self._extract_condition_checks()
        self._extract_valuations()
        self._extract_loans_out()
        self._extract_movements()
        self._extract_grap_assets()
        
        return self._build_jsonld()
    
    def _extract_records_by_parent(self, fonds_id: int):
        query = """
            WITH RECURSIVE hierarchy AS (
                SELECT id, parent_id, identifier, level_of_description_id, 
                       source_culture, repository_id, lft, rgt
                FROM information_object WHERE id = %s
                UNION ALL
                SELECT io.id, io.parent_id, io.identifier, io.level_of_description_id,
                       io.source_culture, io.repository_id, io.lft, io.rgt
                FROM information_object io
                INNER JOIN hierarchy h ON io.parent_id = h.id
            )
            SELECT 
                h.id, h.parent_id, h.identifier, h.repository_id, h.lft, h.rgt,
                ioi.title, ioi.scope_and_content, ioi.arrangement,
                ioi.extent_and_medium, ioi.archival_history, ioi.acquisition,
                ioi.appraisal, ioi.accruals, ioi.physical_characteristics,
                ioi.finding_aids, ioi.location_of_originals, ioi.location_of_copies,
                ioi.related_units_of_description, ioi.rules,
                ti.name as level_of_description, h.source_culture
            FROM hierarchy h
            JOIN information_object_i18n ioi ON h.id = ioi.id 
                AND ioi.culture = COALESCE(h.source_culture, 'en')
            LEFT JOIN term_i18n ti ON h.level_of_description_id = ti.id AND ti.culture = 'en'
        """
        self.cursor.execute(query, (fonds_id,))
        
        for row in self.cursor.fetchall():
            level = (row['level_of_description'] or 'item').lower()
            ric_type = self.LEVEL_TO_RIC.get(level, 'RecordSet')
            
            record = {
                '@id': self.mint_uri(ric_type, row['id']),
                '@type': f'rico:{ric_type}',
                'rico:identifier': row['identifier'],
                'rico:title': row['title'],
                'rico:scopeAndContent': row['scope_and_content'],
                'rico:arrangement': row['arrangement'],
                'rico:extentAndMedium': row['extent_and_medium'],
                'rico:history': row['archival_history'],
                'rico:conditionsOfAccess': row['physical_characteristics'],
                'rico:findingAids': row['finding_aids'],
                '_parent_id': row['parent_id'],
                '_repository_id': row['repository_id'],
                '_db_id': row['id'],
                '_lft': row['lft'],
                '_rgt': row['rgt'],
                '_rules_text': row['rules'],
                '_level': level,
            }
            
            if row['location_of_originals']:
                record['rico:locationOfOriginals'] = row['location_of_originals']
            if row['location_of_copies']:
                record['rico:locationOfCopies'] = row['location_of_copies']
                
            self.records[row['id']] = record
            
            parent_id = row['parent_id']
            if parent_id not in self.record_order:
                self.record_order[parent_id] = []
            self.record_order[parent_id].append((row['lft'], row['id']))
    
    def _extract_agents_by_records(self):
        if not self.records:
            return
            
        record_ids = list(self.records.keys())
        placeholders = ','.join(['%s'] * len(record_ids))
        
        query = f"""
            SELECT DISTINCT
                a.id, a.entity_type_id,
                ai.authorized_form_of_name, ai.dates_of_existence,
                ai.history, ai.places, ai.legal_status,
                ai.functions, ai.mandates, ai.internal_structures,
                ai.general_context, ti.name as entity_type
            FROM actor a
            JOIN actor_i18n ai ON a.id = ai.id AND ai.culture = 'en'
            LEFT JOIN term_i18n ti ON a.entity_type_id = ti.id AND ti.culture = 'en'
            WHERE a.id IN (
                SELECT DISTINCT actor_id FROM event WHERE object_id IN ({placeholders})
            )
        """
        self.cursor.execute(query, record_ids)
        
        for row in self.cursor.fetchall():
            entity_type = (row['entity_type'] or 'person').lower()
            ric_type = self.ACTOR_TYPE_TO_RIC.get(entity_type, 'Agent')
            
            agent = {
                '@id': self.mint_uri(ric_type, row['id']),
                '@type': f'rico:{ric_type}',
                'rico:hasAgentName': {
                    '@type': 'rico:AgentName',
                    'rico:textualValue': row['authorized_form_of_name'],
                },
                'rico:history': row['history'],
                '_db_id': row['id'],
                '_name': row['authorized_form_of_name'],
                '_mandates_text': row['mandates'],
                '_functions_text': row['functions'],
            }
            
            if row['dates_of_existence']:
                agent['rico:hasBeginningDate'] = row['dates_of_existence']
            if row['legal_status']:
                agent['rico:hasOrHadLegalStatus'] = row['legal_status']
                
            self.agents[row['id']] = agent
    
    def _extract_activities_by_records(self):
        if not self.records:
            return
            
        record_ids = list(self.records.keys())
        placeholders = ','.join(['%s'] * len(record_ids))
        
        query = f"""
            SELECT 
                e.id, e.object_id, e.actor_id, e.start_date, e.end_date,
                ei.date as date_display, ei.description, ti.name as event_type
            FROM event e
            LEFT JOIN event_i18n ei ON e.id = ei.id AND ei.culture = 'en'
            LEFT JOIN term_i18n ti ON e.type_id = ti.id AND ti.culture = 'en'
            WHERE e.object_id IN ({placeholders})
        """
        self.cursor.execute(query, record_ids)
        
        for row in self.cursor.fetchall():
            event_type = (row['event_type'] or 'creation').lower()
            ric_activity_type = self.EVENT_TYPE_TO_RIC.get(event_type, 'Activity')
            
            record = self.records.get(row['object_id'])
            agent = self.agents.get(row['actor_id']) if row['actor_id'] else None
            
            if record:
                activity = {
                    '@id': self.mint_uri(ric_activity_type, row['id']),
                    '@type': f'rico:{ric_activity_type}',
                    'rico:hasActivityType': event_type.title(),
                    'rico:resultsOrResultedIn': {'@id': record['@id']},
                    '_event_type': event_type,
                    '_record_id': row['object_id'],
                    '_agent_id': row['actor_id'],
                }
                
                if agent:
                    activity['rico:hasOrHadParticipant'] = {'@id': agent['@id']}
                
                if row['start_date'] or row['end_date']:
                    date_obj = {'@type': 'rico:DateRange'}
                    if row['date_display']:
                        date_obj['rico:expressedDate'] = row['date_display']
                    if row['start_date']:
                        date_obj['rico:beginningDate'] = str(row['start_date'])
                    if row['end_date']:
                        date_obj['rico:endDate'] = str(row['end_date'])
                    activity['rico:isOrWasAssociatedWithDate'] = date_obj
                
                if row['description']:
                    activity['rico:descriptiveNote'] = row['description']
                    
                self.activities[row['id']] = activity
    
    def _extract_access_points(self):
        if not self.records:
            return
            
        record_ids = list(self.records.keys())
        placeholders = ','.join(['%s'] * len(record_ids))
        
        query = f"""
            SELECT 
                otr.object_id, otr.term_id, t.taxonomy_id,
                ti.name as term_name, taxi.name as taxonomy_name
            FROM object_term_relation otr
            JOIN term t ON otr.term_id = t.id
            JOIN term_i18n ti ON t.id = ti.id AND ti.culture = 'en'
            JOIN taxonomy tax ON t.taxonomy_id = tax.id
            JOIN taxonomy_i18n taxi ON tax.id = taxi.id AND taxi.culture = 'en'
            WHERE otr.object_id IN ({placeholders})
        """
        self.cursor.execute(query, record_ids)
        
        first_subject = {}
        
        for row in self.cursor.fetchall():
            record = self.records.get(row['object_id'])
            if not record:
                continue
                
            taxonomy_name = (row['taxonomy_name'] or '').lower()
            term_id = row['term_id']
            term_name = row['term_name']
            
            if 'subject' in taxonomy_name:
                if term_id not in self.subjects:
                    self.subjects[term_id] = {
                        '@id': self.mint_uri('concept', term_id),
                        '@type': 'rico:Thing',
                        'rico:hasOrHadName': {
                            '@type': 'rico:Name',
                            'rico:textualValue': term_name,
                        },
                        '_name': term_name,
                    }
                self.relations.append({
                    'from': record['@id'],
                    'to': self.subjects[term_id]['@id'],
                    'predicate': 'rico:hasOrHadSubject',
                })
                if row['object_id'] not in first_subject:
                    first_subject[row['object_id']] = term_id
                    self.relations.append({
                        'from': record['@id'],
                        'to': self.subjects[term_id]['@id'],
                        'predicate': 'rico:hasOrHadMainSubject',
                    })
                
            elif 'place' in taxonomy_name:
                if term_id not in self.places:
                    self.places[term_id] = {
                        '@id': self.mint_uri('place', term_id),
                        '@type': 'rico:Place',
                        'rico:hasPlaceName': {
                            '@type': 'rico:PlaceName',
                            'rico:textualValue': term_name,
                        },
                        '_name': term_name,
                    }
                self.relations.append({
                    'from': record['@id'],
                    'to': self.places[term_id]['@id'],
                    'predicate': 'rico:hasOrHadPlaceOfOrigin',
                })
                
            elif 'genre' in taxonomy_name or 'type' in taxonomy_name:
                if term_id not in self.genres:
                    self.genres[term_id] = {
                        '@id': self.mint_uri('documentaryformtype', term_id),
                        '@type': 'rico:DocumentaryFormType',
                        'rico:hasOrHadName': {
                            '@type': 'rico:Name',
                            'rico:textualValue': term_name,
                        },
                    }
                self.relations.append({
                    'from': record['@id'],
                    'to': self.genres[term_id]['@id'],
                    'predicate': 'rico:hasOrHadContentOfType',
                })
    
    def _extract_digital_objects(self):
        if not self.records:
            return
            
        record_ids = list(self.records.keys())
        placeholders = ','.join(['%s'] * len(record_ids))
        
        query = f"""
            SELECT 
                do.id, do.object_id, do.usage_id,
                do.mime_type, do.byte_size, do.name,
                ti.name as usage_type
            FROM digital_object do
            LEFT JOIN term_i18n ti ON do.usage_id = ti.id AND ti.culture = 'en'
            WHERE do.object_id IN ({placeholders})
        """
        self.cursor.execute(query, record_ids)
        
        for row in self.cursor.fetchall():
            record = self.records.get(row['object_id'])
            if not record:
                continue
                
            instantiation = {
                '@id': self.mint_uri('instantiation', row['id']),
                '@type': 'rico:Instantiation',
                'rico:title': row['name'],
                'rico:hasCarrierType': {
                    '@type': 'rico:CarrierType',
                    'rico:hasOrHadName': {
                        '@type': 'rico:Name',
                        'rico:textualValue': 'Digital',
                    },
                },
            }
            
            if row['mime_type']:
                instantiation['rico:hasMimeType'] = row['mime_type']
            if row['byte_size']:
                instantiation['rico:hasExtent'] = {
                    '@type': 'rico:Extent',
                    'rico:quantity': row['byte_size'],
                    'rico:unitOfMeasurement': 'bytes',
                }
                
            self.instantiations[row['id']] = instantiation
            self.relations.append({
                'from': record['@id'],
                'to': instantiation['@id'],
                'predicate': 'rico:hasInstantiation',
            })
    
    def _extract_related_materials(self):
        if not self.records:
            return
            
        record_ids = list(self.records.keys())
        placeholders = ','.join(['%s'] * len(record_ids))
        
        query = f"""
            SELECT r.id, r.subject_id, r.object_id, r.type_id, ti.name as relation_type
            FROM relation r
            LEFT JOIN term_i18n ti ON r.type_id = ti.id AND ti.culture = 'en'
            WHERE r.subject_id IN ({placeholders}) OR r.object_id IN ({placeholders})
        """
        self.cursor.execute(query, record_ids + record_ids)
        
        for row in self.cursor.fetchall():
            subject_record = self.records.get(row['subject_id'])
            object_record = self.records.get(row['object_id'])
            
            if subject_record and object_record:
                self.relations.append({
                    'from': subject_record['@id'],
                    'to': object_record['@id'],
                    'predicate': 'rico:isAssociatedWith',
                    'note': row['relation_type'],
                })
    
    def _extract_rights_and_rules(self):
        if not self.records:
            return
            
        record_ids = list(self.records.keys())
        placeholders = ','.join(['%s'] * len(record_ids))
        
        query = f"""
            SELECT 
                r.id, r.start_date, r.end_date, r.basis_id,
                r.copyright_status_id, r.copyright_jurisdiction,
                ri.rights_note, ri.copyright_note, ri.license_terms,
                ri.statute_jurisdiction, ri.statute_note,
                rr.object_id, tb.name as basis_name, ts.name as status_name
            FROM rights r
            JOIN rights_i18n ri ON r.id = ri.id AND ri.culture = 'en'
            JOIN rights_record rr ON r.id = rr.rights_id
            LEFT JOIN term_i18n tb ON r.basis_id = tb.id AND tb.culture = 'en'
            LEFT JOIN term_i18n ts ON r.copyright_status_id = ts.id AND ts.culture = 'en'
            WHERE rr.object_id IN ({placeholders})
        """
        try:
            self.cursor.execute(query, record_ids)
            
            for row in self.cursor.fetchall():
                record = self.records.get(row['object_id'])
                if not record:
                    continue
                
                basis = (row['basis_name'] or 'other').lower()
                rule_type = 'Rule'
                if 'copyright' in basis:
                    rule_type = 'CopyrightRule'
                elif 'license' in basis:
                    rule_type = 'LicenseRule'
                elif 'statute' in basis:
                    rule_type = 'StatuteRule'
                
                rule = {
                    '@id': self.mint_uri('rule', row['id']),
                    '@type': f'rico:{rule_type}',
                    'rico:ruleType': row['basis_name'] or 'Unspecified',
                }
                
                notes = []
                if row['rights_note']: notes.append(row['rights_note'])
                if row['copyright_note']: notes.append(row['copyright_note'])
                if row['license_terms']: notes.append(f"License: {row['license_terms']}")
                if row['statute_note']: notes.append(f"Statute: {row['statute_note']}")
                if notes:
                    rule['rico:descriptiveNote'] = '; '.join(notes)
                
                jurisdiction = row['copyright_jurisdiction'] or row['statute_jurisdiction']
                if jurisdiction:
                    rule['rico:hasOrHadJurisdiction'] = jurisdiction
                
                if row['start_date'] or row['end_date']:
                    rule['rico:isOrWasAssociatedWithDate'] = {
                        '@type': 'rico:DateRange',
                        'rico:beginningDate': str(row['start_date']) if row['start_date'] else None,
                        'rico:endDate': str(row['end_date']) if row['end_date'] else None,
                    }
                
                if row['status_name']:
                    rule['rico:hasStatus'] = row['status_name']
                
                self.rules[row['id']] = rule
                self.relations.append({
                    'from': record['@id'],
                    'to': rule['@id'],
                    'predicate': 'rico:isOrWasRegulatedBy',
                })
        except Exception as e:
            print(f"Warning: Could not extract rights: {e}")
        
        for record_id, record in self.records.items():
            rules_text = record.get('_rules_text')
            if rules_text:
                rule_id = f"text_{record_id}"
                rule = {
                    '@id': self.mint_uri('rule', rule_id),
                    '@type': 'rico:Rule',
                    'rico:ruleType': 'DescriptionRules',
                    'rico:descriptiveNote': rules_text,
                }
                self.rules[rule_id] = rule
                self.relations.append({
                    'from': record['@id'],
                    'to': rule['@id'],
                    'predicate': 'rico:isDescribedBy',
                })
    
    def _extract_functions(self):
        agent_ids = list(self.agents.keys())
        if not agent_ids:
            return
            
        query = """
            SELECT 
                fo.id, fo.type_id,
                foi.authorized_form_of_name, foi.classification,
                foi.dates, foi.description, foi.history, foi.legislation,
                ti.name as function_type
            FROM function_object fo
            JOIN function_object_i18n foi ON fo.id = foi.id AND foi.culture = 'en'
            LEFT JOIN term_i18n ti ON fo.type_id = ti.id AND ti.culture = 'en'
        """
        try:
            self.cursor.execute(query)
            
            for row in self.cursor.fetchall():
                function = {
                    '@id': self.mint_uri('function', row['id']),
                    '@type': 'rico:Function',
                    'rico:hasOrHadName': {
                        '@type': 'rico:Name',
                        'rico:textualValue': row['authorized_form_of_name'],
                    },
                }
                if row['description']:
                    function['rico:descriptiveNote'] = row['description']
                if row['classification']:
                    function['rico:classification'] = row['classification']
                if row['history']:
                    function['rico:history'] = row['history']
                if row['legislation']:
                    function['rico:isOrWasRegulatedBy'] = row['legislation']
                if row['dates']:
                    function['rico:expressedDate'] = row['dates']
                    
                self.functions[row['id']] = function
        except Exception as e:
            print(f"Warning: Could not extract functions: {e}")
        
        for agent_id, agent in self.agents.items():
            functions_text = agent.get('_functions_text')
            if functions_text:
                func_id = f"agent_{agent_id}"
                function = {
                    '@id': self.mint_uri('function', func_id),
                    '@type': 'rico:Function',
                    'rico:descriptiveNote': functions_text,
                }
                self.functions[func_id] = function
                self.relations.append({
                    'from': agent['@id'],
                    'to': function['@id'],
                    'predicate': 'rico:hasOrHadFunction',
                })
    
    def _extract_mandates_from_agents(self):
        for agent_id, agent in self.agents.items():
            mandates_text = agent.get('_mandates_text')
            if mandates_text:
                mandate = {
                    '@id': self.mint_uri('mandate', agent_id),
                    '@type': 'rico:Mandate',
                    'rico:descriptiveNote': mandates_text,
                }
                self.mandates[agent_id] = mandate
                self.relations.append({
                    'from': agent['@id'],
                    'to': mandate['@id'],
                    'predicate': 'rico:isOrWasRegulatedBy',
                })
    
    def _extract_repository(self, fonds_id: int):
        query = """
            SELECT 
                r.id, ai.authorized_form_of_name, ai.history,
                ci.contact_person, ci.street_address, ci.postal_code,
                ci.country_code, ci.email, ci.website
            FROM repository r
            JOIN actor_i18n ai ON r.id = ai.id AND ai.culture = 'en'
            LEFT JOIN contact_information ci ON r.id = ci.actor_id
            JOIN information_object io ON io.repository_id = r.id
            WHERE io.id = %s LIMIT 1
        """
        self.cursor.execute(query, (fonds_id,))
        row = self.cursor.fetchone()
        
        if row:
            self.repository = {
                '@id': self.mint_uri('corporatebody', row['id']),
                '@type': 'rico:CorporateBody',
                'rico:hasAgentName': {
                    '@type': 'rico:AgentName',
                    'rico:textualValue': row['authorized_form_of_name'],
                },
                'rico:history': row['history'],
                '_is_repository': True,
                '_db_id': row['id'],
            }
            
            address_parts = [p for p in [row['street_address'], row['postal_code'], row['country_code']] if p]
            if address_parts:
                self.repository['rico:hasOrHadLocation'] = {
                    '@type': 'rico:Place',
                    'rico:hasPlaceName': {
                        '@type': 'rico:PlaceName',
                        'rico:textualValue': ', '.join(address_parts),
                    },
                }
                
            contacts = []
            if row['email']: contacts.append(f"Email: {row['email']}")
            if row['website']: contacts.append(f"Website: {row['website']}")
            if contacts:
                self.repository['rico:descriptiveNote'] = '; '.join(contacts)
    
    def _build_creator_shortcuts(self):
        for activity_id, activity in self.activities.items():
            event_type = activity.get('_event_type', '')
            record_id = activity.get('_record_id')
            agent_id = activity.get('_agent_id')
            
            if not record_id or not agent_id:
                continue
                
            record = self.records.get(record_id)
            agent = self.agents.get(agent_id)
            
            if not record or not agent:
                continue
            
            if event_type in ['creation', 'contribution', 'written']:
                self.relations.append({
                    'from': record['@id'],
                    'to': agent['@id'],
                    'predicate': 'rico:hasCreator',
                })
            elif event_type in ['accumulation', 'collection']:
                self.relations.append({
                    'from': record['@id'],
                    'to': agent['@id'],
                    'predicate': 'rico:hasAccumulator',
                })
    
    def _build_temporal_relations(self):
        for parent_id, children in self.record_order.items():
            sorted_children = sorted(children, key=lambda x: x[0])
            
            for i in range(len(sorted_children) - 1):
                current_id = sorted_children[i][1]
                next_id = sorted_children[i + 1][1]
                
                current_record = self.records.get(current_id)
                next_record = self.records.get(next_id)
                
                if current_record and next_record:
                    self.relations.append({
                        'from': current_record['@id'],
                        'to': next_record['@id'],
                        'predicate': 'rico:precedes',
                    })
                    self.relations.append({
                        'from': next_record['@id'],
                        'to': current_record['@id'],
                        'predicate': 'rico:follows',
                    })
        
        for record_id, record in self.records.items():
            parent_id = record.get('_parent_id')
            if parent_id and parent_id in self.records:
                parent = self.records[parent_id]
                self.relations.append({
                    'from': parent['@id'],
                    'to': record['@id'],
                    'predicate': 'rico:includes',
                })
    
    def _build_equivalence_candidates(self):
        name_groups = defaultdict(list)
        for agent_id, agent in self.agents.items():
            name = agent.get('_name', '')
            if name:
                normalized = ''.join(c.lower() for c in name if c.isalnum() or c.isspace()).strip()
                name_groups[normalized].append(agent)
        
        for normalized_name, agents in name_groups.items():
            if len(agents) > 1:
                for i in range(len(agents)):
                    for j in range(i + 1, len(agents)):
                        self.relations.append({
                            'from': agents[i]['@id'],
                            'to': agents[j]['@id'],
                            'predicate': 'rico:isEquivalentTo',
                            'note': 'name-match',
                        })
    
    # ==================== PHASE 5: SPECTRUM/GRAP EXTRACTION ====================
    
    def _extract_condition_checks(self):
        """Extract condition assessments from spectrum_condition_check."""
        if not self.records:
            return
            
        record_ids = list(self.records.keys())
        placeholders = ','.join(['%s'] * len(record_ids))
        
        query = f"""
            SELECT 
                id, object_id, condition_reference, check_date, check_reason,
                checked_by, overall_condition, condition_note, completeness_note,
                hazard_note, technical_assessment, recommended_treatment,
                treatment_priority, next_check_date, environment_recommendation,
                handling_recommendation, display_recommendation, storage_recommendation,
                packing_recommendation, photo_count, workflow_state, condition_rating,
                material_type
            FROM spectrum_condition_check
            WHERE object_id IN ({placeholders})
            ORDER BY check_date DESC
        """
        try:
            self.cursor.execute(query, record_ids)
            
            for row in self.cursor.fetchall():
                record = self.records.get(row['object_id'])
                if not record:
                    continue
                
                check_id = f"condition_{row['id']}"
                activity = {
                    '@id': self.mint_uri('activity', check_id),
                    '@type': 'rico:Activity',
                    'rico:hasActivityType': 'ConditionCheck',
                    'rico:resultsOrResultedIn': {'@id': record['@id']},
                    # Spectrum extensions
                    'spectrum:conditionReference': row['condition_reference'],
                    'spectrum:checkedBy': row['checked_by'],
                    'spectrum:workflowState': row['workflow_state'],
                }
                
                # Date
                if row['check_date']:
                    activity['rico:isOrWasAssociatedWithDate'] = {
                        '@type': 'rico:DateRange',
                        'rico:beginningDate': str(row['check_date']),
                        'rico:expressedDate': str(row['check_date']),
                    }
                
                # Condition rating
                rating = row['overall_condition'] or row['condition_rating']
                if rating:
                    activity['spectrum:overallCondition'] = rating
                
                # Reason
                if row['check_reason']:
                    activity['spectrum:checkReason'] = row['check_reason']
                
                # Notes - combine into descriptive note
                notes = []
                if row['condition_note']: notes.append(f"Condition: {row['condition_note']}")
                if row['completeness_note']: notes.append(f"Completeness: {row['completeness_note']}")
                if row['hazard_note']: notes.append(f"Hazards: {row['hazard_note']}")
                if row['technical_assessment']: notes.append(f"Technical: {row['technical_assessment']}")
                if notes:
                    activity['rico:descriptiveNote'] = ' | '.join(notes)
                
                # Recommendations
                recommendations = []
                if row['recommended_treatment']: recommendations.append(f"Treatment: {row['recommended_treatment']}")
                if row['environment_recommendation']: recommendations.append(f"Environment: {row['environment_recommendation']}")
                if row['handling_recommendation']: recommendations.append(f"Handling: {row['handling_recommendation']}")
                if row['display_recommendation']: recommendations.append(f"Display: {row['display_recommendation']}")
                if row['storage_recommendation']: recommendations.append(f"Storage: {row['storage_recommendation']}")
                if row['packing_recommendation']: recommendations.append(f"Packing: {row['packing_recommendation']}")
                if recommendations:
                    activity['spectrum:recommendations'] = recommendations
                
                # Priority
                if row['treatment_priority']:
                    activity['spectrum:treatmentPriority'] = row['treatment_priority']
                
                # Next check due
                if row['next_check_date']:
                    activity['spectrum:nextCheckDate'] = str(row['next_check_date'])
                
                # Material type
                if row['material_type']:
                    activity['spectrum:materialType'] = row['material_type']
                
                # Photo count
                if row['photo_count']:
                    activity['spectrum:photoCount'] = row['photo_count']
                
                self.condition_checks[row['id']] = activity
                
        except Exception as e:
            print(f"Warning: Could not extract condition checks: {e}")
    
    def _extract_valuations(self):
        """Extract valuations from spectrum_valuation."""
        if not self.records:
            return
            
        record_ids = list(self.records.keys())
        placeholders = ','.join(['%s'] * len(record_ids))
        
        query = f"""
            SELECT 
                id, object_id, valuation_reference, valuation_date,
                valuation_type, valuation_amount, valuation_currency,
                valuer_name, valuer_organization, valuation_note,
                renewal_date, is_current, workflow_state, currency
            FROM spectrum_valuation
            WHERE object_id IN ({placeholders})
            ORDER BY valuation_date DESC
        """
        try:
            self.cursor.execute(query, record_ids)
            
            for row in self.cursor.fetchall():
                record = self.records.get(row['object_id'])
                if not record:
                    continue
                
                val_id = f"valuation_{row['id']}"
                activity = {
                    '@id': self.mint_uri('activity', val_id),
                    '@type': 'rico:Activity',
                    'rico:hasActivityType': 'Valuation',
                    'rico:resultsOrResultedIn': {'@id': record['@id']},
                    # Spectrum extensions
                    'spectrum:valuationReference': row['valuation_reference'],
                    'spectrum:workflowState': row['workflow_state'],
                }
                
                # Date
                if row['valuation_date']:
                    activity['rico:isOrWasAssociatedWithDate'] = {
                        '@type': 'rico:DateRange',
                        'rico:beginningDate': str(row['valuation_date']),
                    }
                
                # Valuation type
                if row['valuation_type']:
                    activity['spectrum:valuationType'] = row['valuation_type']
                
                # Amount and currency
                currency = row['valuation_currency'] or row['currency'] or 'ZAR'
                if row['valuation_amount']:
                    activity['spectrum:valuationAmount'] = {
                        '@type': 'spectrum:MonetaryAmount',
                        'spectrum:amount': float(row['valuation_amount']),
                        'spectrum:currency': currency,
                    }
                
                # Valuer
                valuer_parts = []
                if row['valuer_name']: valuer_parts.append(row['valuer_name'])
                if row['valuer_organization']: valuer_parts.append(f"({row['valuer_organization']})")
                if valuer_parts:
                    activity['spectrum:valuer'] = ' '.join(valuer_parts)
                
                # Notes
                if row['valuation_note']:
                    activity['rico:descriptiveNote'] = row['valuation_note']
                
                # Renewal date
                if row['renewal_date']:
                    activity['spectrum:renewalDate'] = str(row['renewal_date'])
                
                # Current status
                if row['is_current']:
                    activity['spectrum:isCurrent'] = bool(row['is_current'])
                
                self.valuations[row['id']] = activity
                
        except Exception as e:
            print(f"Warning: Could not extract valuations: {e}")
    
    def _extract_loans_out(self):
        """Extract outgoing loans from spectrum_loan_out."""
        if not self.records:
            return
            
        record_ids = list(self.records.keys())
        placeholders = ','.join(['%s'] * len(record_ids))
        
        query = f"""
            SELECT 
                id, object_id, loan_out_number, loan_number,
                borrower_name, borrower_contact, borrower_address,
                venue_name, venue_address,
                loan_out_date, loan_start_date, loan_return_date, loan_end_date,
                actual_return_date, loan_purpose, loan_conditions,
                insurance_value, insurance_currency, insurance_reference,
                insurance_provider, insurance_policy_number,
                loan_agreement_date, loan_agreement_reference,
                exhibition_title, exhibition_dates,
                special_requirements, special_conditions,
                courier_required, courier_name,
                loan_status, workflow_state, loan_note, loan_out_note
            FROM spectrum_loan_out
            WHERE object_id IN ({placeholders})
            ORDER BY loan_out_date DESC
        """
        try:
            self.cursor.execute(query, record_ids)
            
            for row in self.cursor.fetchall():
                record = self.records.get(row['object_id'])
                if not record:
                    continue
                
                loan_id = f"loan_out_{row['id']}"
                loan_number = row['loan_out_number'] or row['loan_number']
                
                activity = {
                    '@id': self.mint_uri('activity', loan_id),
                    '@type': 'rico:Activity',
                    'rico:hasActivityType': 'LoanOut',
                    'rico:resultsOrResultedIn': {'@id': record['@id']},
                    # Spectrum extensions
                    'spectrum:loanNumber': loan_number,
                    'spectrum:loanStatus': row['loan_status'],
                    'spectrum:workflowState': row['workflow_state'],
                }
                
                # Dates
                start_date = row['loan_out_date'] or row['loan_start_date']
                end_date = row['loan_return_date'] or row['loan_end_date']
                if start_date or end_date:
                    activity['rico:isOrWasAssociatedWithDate'] = {
                        '@type': 'rico:DateRange',
                        'rico:beginningDate': str(start_date) if start_date else None,
                        'rico:endDate': str(end_date) if end_date else None,
                    }
                
                if row['actual_return_date']:
                    activity['spectrum:actualReturnDate'] = str(row['actual_return_date'])
                
                # Borrower
                if row['borrower_name']:
                    activity['spectrum:borrower'] = {
                        '@type': 'spectrum:Borrower',
                        'spectrum:name': row['borrower_name'],
                        'spectrum:contact': row['borrower_contact'],
                        'spectrum:address': row['borrower_address'],
                    }
                
                # Venue
                if row['venue_name']:
                    activity['spectrum:venue'] = {
                        '@type': 'spectrum:Venue',
                        'spectrum:name': row['venue_name'],
                        'spectrum:address': row['venue_address'],
                    }
                
                # Purpose
                if row['loan_purpose']:
                    activity['spectrum:loanPurpose'] = row['loan_purpose']
                
                # Exhibition
                if row['exhibition_title']:
                    activity['spectrum:exhibitionTitle'] = row['exhibition_title']
                if row['exhibition_dates']:
                    activity['spectrum:exhibitionDates'] = row['exhibition_dates']
                
                # Insurance
                if row['insurance_value']:
                    activity['spectrum:insuranceValue'] = {
                        '@type': 'spectrum:MonetaryAmount',
                        'spectrum:amount': float(row['insurance_value']),
                        'spectrum:currency': row['insurance_currency'] or 'ZAR',
                    }
                if row['insurance_provider']:
                    activity['spectrum:insuranceProvider'] = row['insurance_provider']
                if row['insurance_policy_number']:
                    activity['spectrum:insurancePolicyNumber'] = row['insurance_policy_number']
                
                # Agreement
                if row['loan_agreement_reference']:
                    activity['spectrum:agreementReference'] = row['loan_agreement_reference']
                if row['loan_agreement_date']:
                    activity['spectrum:agreementDate'] = str(row['loan_agreement_date'])
                
                # Conditions and requirements
                conditions = []
                if row['loan_conditions']: conditions.append(row['loan_conditions'])
                if row['special_conditions']: conditions.append(row['special_conditions'])
                if row['special_requirements']: conditions.append(row['special_requirements'])
                if conditions:
                    activity['spectrum:loanConditions'] = ' | '.join(conditions)
                
                # Courier
                if row['courier_required']:
                    activity['spectrum:courierRequired'] = bool(row['courier_required'])
                if row['courier_name']:
                    activity['spectrum:courierName'] = row['courier_name']
                
                # Notes
                notes = row['loan_note'] or row['loan_out_note']
                if notes:
                    activity['rico:descriptiveNote'] = notes
                
                self.loans_out[row['id']] = activity
                
        except Exception as e:
            print(f"Warning: Could not extract loans out: {e}")
    
    def _extract_movements(self):
        """Extract location movements from spectrum_movement."""
        if not self.records:
            return
            
        record_ids = list(self.records.keys())
        placeholders = ','.join(['%s'] * len(record_ids))
        
        query = f"""
            SELECT 
                m.id, m.object_id, m.movement_reference, m.movement_date,
                m.movement_reason, m.movement_method, m.movement_contact,
                m.handler_name, m.moved_by, m.condition_before, m.condition_after,
                m.planned_return_date, m.actual_return_date,
                m.movement_note, m.removal_authorization, m.authorization_date,
                m.workflow_state,
                lf.location_name as from_location,
                lt.location_name as to_location
            FROM spectrum_movement m
            LEFT JOIN spectrum_location lf ON m.location_from = lf.id OR m.from_location_id = lf.id
            LEFT JOIN spectrum_location lt ON m.location_to = lt.id OR m.to_location_id = lt.id
            WHERE m.object_id IN ({placeholders})
            ORDER BY m.movement_date DESC
        """
        try:
            self.cursor.execute(query, record_ids)
            
            for row in self.cursor.fetchall():
                record = self.records.get(row['object_id'])
                if not record:
                    continue
                
                move_id = f"movement_{row['id']}"
                activity = {
                    '@id': self.mint_uri('activity', move_id),
                    '@type': 'rico:Activity',
                    'rico:hasActivityType': 'LocationMovement',
                    'rico:resultsOrResultedIn': {'@id': record['@id']},
                    # Spectrum extensions
                    'spectrum:movementReference': row['movement_reference'],
                    'spectrum:workflowState': row['workflow_state'],
                }
                
                # Date
                if row['movement_date']:
                    activity['rico:isOrWasAssociatedWithDate'] = {
                        '@type': 'rico:DateRange',
                        'rico:beginningDate': str(row['movement_date']),
                    }
                
                # Reason and method
                if row['movement_reason']:
                    activity['spectrum:movementReason'] = row['movement_reason']
                if row['movement_method']:
                    activity['spectrum:movementMethod'] = row['movement_method']
                
                # Locations
                if row['from_location']:
                    activity['spectrum:fromLocation'] = row['from_location']
                if row['to_location']:
                    activity['spectrum:toLocation'] = row['to_location']
                
                # Handler
                handler = row['handler_name'] or row['moved_by']
                if handler:
                    activity['spectrum:movedBy'] = handler
                if row['movement_contact']:
                    activity['spectrum:movementContact'] = row['movement_contact']
                
                # Condition before/after
                if row['condition_before']:
                    activity['spectrum:conditionBefore'] = row['condition_before']
                if row['condition_after']:
                    activity['spectrum:conditionAfter'] = row['condition_after']
                
                # Return dates
                if row['planned_return_date']:
                    activity['spectrum:plannedReturnDate'] = str(row['planned_return_date'])
                if row['actual_return_date']:
                    activity['spectrum:actualReturnDate'] = str(row['actual_return_date'])
                
                # Authorization
                if row['removal_authorization']:
                    activity['spectrum:removalAuthorization'] = row['removal_authorization']
                if row['authorization_date']:
                    activity['spectrum:authorizationDate'] = str(row['authorization_date'])
                
                # Notes
                if row['movement_note']:
                    activity['rico:descriptiveNote'] = row['movement_note']
                
                self.movements[row['id']] = activity
                
        except Exception as e:
            print(f"Warning: Could not extract movements: {e}")
    
    def _extract_grap_assets(self):
        """Extract GRAP heritage asset data."""
        if not self.records:
            return
            
        record_ids = list(self.records.keys())
        placeholders = ','.join(['%s'] * len(record_ids))
        
        # Try grap_heritage_asset first (linked to information_object)
        query = f"""
            SELECT 
                g.id, g.object_id,
                g.recognition_status, g.recognition_status_reason,
                g.recognition_date, g.measurement_basis,
                g.acquisition_method, g.acquisition_date,
                g.cost_of_acquisition, g.fair_value_at_acquisition,
                g.nominal_value, g.donor_name, g.donor_restrictions,
                g.initial_carrying_amount, g.current_carrying_amount,
                g.last_valuation_date, g.last_valuation_amount,
                g.valuation_method, g.valuer_name, g.valuer_credentials,
                g.revaluation_frequency, g.revaluation_surplus,
                g.depreciation_policy, g.useful_life_years,
                g.residual_value, g.depreciation_method,
                g.annual_depreciation, g.accumulated_depreciation,
                g.last_impairment_date, g.impairment_indicators,
                g.impairment_indicators_details, g.impairment_loss,
                g.asset_class, g.asset_sub_class,
                g.gl_account_code, g.cost_center, g.fund_source,
                g.heritage_significance, g.significance_statement,
                g.restrictions_on_use, g.restrictions_on_disposal,
                g.conservation_requirements, g.conservation_commitments,
                g.insurance_required, g.insurance_value,
                g.insurance_policy_number, g.insurance_provider,
                g.insurance_expiry_date, g.risk_level,
                g.current_location, g.condition_rating
            FROM grap_heritage_asset g
            WHERE g.object_id IN ({placeholders})
        """
        try:
            self.cursor.execute(query, record_ids)
            
            for row in self.cursor.fetchall():
                record = self.records.get(row['object_id'])
                if not record:
                    continue
                
                grap_id = f"grap_{row['id']}"
                
                # GRAP data extends the record with financial/heritage properties
                grap_asset = {
                    '@id': self.mint_uri('grap-asset', grap_id),
                    '@type': ['grap:HeritageAsset'],
                    'grap:relatedRecord': {'@id': record['@id']},
                    
                    # Recognition
                    'grap:recognitionStatus': row['recognition_status'],
                    'grap:recognitionStatusReason': row['recognition_status_reason'],
                    'grap:measurementBasis': row['measurement_basis'],
                    
                    # Classification
                    'grap:assetClass': row['asset_class'],
                    'grap:assetSubClass': row['asset_sub_class'],
                    'grap:heritageSignificance': row['heritage_significance'],
                }
                
                # Recognition date
                if row['recognition_date']:
                    grap_asset['grap:recognitionDate'] = str(row['recognition_date'])
                
                # Acquisition
                if row['acquisition_date']:
                    grap_asset['grap:acquisitionDate'] = str(row['acquisition_date'])
                if row['acquisition_method']:
                    grap_asset['grap:acquisitionMethod'] = row['acquisition_method']
                if row['cost_of_acquisition']:
                    grap_asset['grap:costOfAcquisition'] = {
                        '@type': 'grap:MonetaryAmount',
                        'grap:amount': float(row['cost_of_acquisition']),
                        'grap:currency': 'ZAR',
                    }
                if row['fair_value_at_acquisition']:
                    grap_asset['grap:fairValueAtAcquisition'] = {
                        '@type': 'grap:MonetaryAmount',
                        'grap:amount': float(row['fair_value_at_acquisition']),
                        'grap:currency': 'ZAR',
                    }
                
                # Donor
                if row['donor_name']:
                    grap_asset['grap:donorName'] = row['donor_name']
                if row['donor_restrictions']:
                    grap_asset['grap:donorRestrictions'] = row['donor_restrictions']
                
                # Carrying amounts
                if row['initial_carrying_amount']:
                    grap_asset['grap:initialCarryingAmount'] = float(row['initial_carrying_amount'])
                if row['current_carrying_amount']:
                    grap_asset['grap:currentCarryingAmount'] = float(row['current_carrying_amount'])
                if row['nominal_value']:
                    grap_asset['grap:nominalValue'] = float(row['nominal_value'])
                
                # Valuation
                if row['last_valuation_date']:
                    grap_asset['grap:lastValuationDate'] = str(row['last_valuation_date'])
                if row['last_valuation_amount']:
                    grap_asset['grap:lastValuationAmount'] = float(row['last_valuation_amount'])
                if row['valuation_method']:
                    grap_asset['grap:valuationMethod'] = row['valuation_method']
                if row['valuer_name']:
                    grap_asset['grap:valuerName'] = row['valuer_name']
                if row['valuer_credentials']:
                    grap_asset['grap:valuerCredentials'] = row['valuer_credentials']
                if row['revaluation_frequency']:
                    grap_asset['grap:revaluationFrequency'] = row['revaluation_frequency']
                if row['revaluation_surplus']:
                    grap_asset['grap:revaluationSurplus'] = float(row['revaluation_surplus'])
                
                # Depreciation
                if row['depreciation_policy']:
                    grap_asset['grap:depreciationPolicy'] = row['depreciation_policy']
                if row['depreciation_method']:
                    grap_asset['grap:depreciationMethod'] = row['depreciation_method']
                if row['useful_life_years']:
                    grap_asset['grap:usefulLifeYears'] = row['useful_life_years']
                if row['residual_value']:
                    grap_asset['grap:residualValue'] = float(row['residual_value'])
                if row['annual_depreciation']:
                    grap_asset['grap:annualDepreciation'] = float(row['annual_depreciation'])
                if row['accumulated_depreciation']:
                    grap_asset['grap:accumulatedDepreciation'] = float(row['accumulated_depreciation'])
                
                # Impairment
                if row['last_impairment_date']:
                    grap_asset['grap:lastImpairmentDate'] = str(row['last_impairment_date'])
                if row['impairment_indicators']:
                    grap_asset['grap:impairmentIndicators'] = bool(row['impairment_indicators'])
                if row['impairment_indicators_details']:
                    grap_asset['grap:impairmentIndicatorsDetails'] = row['impairment_indicators_details']
                if row['impairment_loss']:
                    grap_asset['grap:impairmentLoss'] = float(row['impairment_loss'])
                
                # Accounting codes
                if row['gl_account_code']:
                    grap_asset['grap:glAccountCode'] = row['gl_account_code']
                if row['cost_center']:
                    grap_asset['grap:costCenter'] = row['cost_center']
                if row['fund_source']:
                    grap_asset['grap:fundSource'] = row['fund_source']
                
                # Significance
                if row['significance_statement']:
                    grap_asset['grap:significanceStatement'] = row['significance_statement']
                
                # Restrictions
                if row['restrictions_on_use']:
                    grap_asset['grap:restrictionsOnUse'] = row['restrictions_on_use']
                if row['restrictions_on_disposal']:
                    grap_asset['grap:restrictionsOnDisposal'] = row['restrictions_on_disposal']
                
                # Conservation
                if row['conservation_requirements']:
                    grap_asset['grap:conservationRequirements'] = row['conservation_requirements']
                if row['conservation_commitments']:
                    grap_asset['grap:conservationCommitments'] = row['conservation_commitments']
                
                # Insurance
                if row['insurance_required'] is not None:
                    grap_asset['grap:insuranceRequired'] = bool(row['insurance_required'])
                if row['insurance_value']:
                    grap_asset['grap:insuranceValue'] = {
                        '@type': 'grap:MonetaryAmount',
                        'grap:amount': float(row['insurance_value']),
                        'grap:currency': 'ZAR',
                    }
                if row['insurance_policy_number']:
                    grap_asset['grap:insurancePolicyNumber'] = row['insurance_policy_number']
                if row['insurance_provider']:
                    grap_asset['grap:insuranceProvider'] = row['insurance_provider']
                if row['insurance_expiry_date']:
                    grap_asset['grap:insuranceExpiryDate'] = str(row['insurance_expiry_date'])
                
                # Risk and condition
                if row['risk_level']:
                    grap_asset['grap:riskLevel'] = row['risk_level']
                if row['current_location']:
                    grap_asset['grap:currentLocation'] = row['current_location']
                if row['condition_rating']:
                    grap_asset['grap:conditionRating'] = row['condition_rating']
                
                # Clean up None values
                grap_asset = {k: v for k, v in grap_asset.items() if v is not None}
                
                self.grap_assets[row['id']] = grap_asset
                
                # Add relation from record to GRAP asset
                self.relations.append({
                    'from': record['@id'],
                    'to': grap_asset['@id'],
                    'predicate': 'grap:hasHeritageAssetData',
                })
                
        except Exception as e:
            print(f"Warning: Could not extract GRAP heritage assets: {e}")
        
        # Also try spectrum_grap_data (alternative table)
        query2 = f"""
            SELECT 
                g.id, g.information_object_id,
                g.recognition_status, g.recognition_status_reason,
                g.measurement_basis, g.initial_recognition_date,
                g.initial_recognition_value, g.carrying_amount,
                g.acquisition_method_grap, g.cost_of_acquisition,
                g.fair_value_at_acquisition, g.donor_restrictions,
                g.last_revaluation_date, g.revaluation_amount,
                g.valuer_credentials, g.valuation_method,
                g.revaluation_frequency, g.depreciation_policy,
                g.useful_life_years, g.residual_value,
                g.depreciation_method, g.accumulated_depreciation,
                g.last_impairment_assessment_date, g.impairment_indicators,
                g.impairment_indicators_details, g.impairment_loss_amount,
                g.asset_class, g.gl_account_code, g.cost_center,
                g.fund_source, g.restrictions_use_disposal,
                g.heritage_significance_rating, g.conservation_commitments,
                g.insurance_coverage_required, g.insurance_coverage_actual
            FROM spectrum_grap_data g
            WHERE g.information_object_id IN ({placeholders})
        """
        try:
            self.cursor.execute(query2, record_ids)
            
            for row in self.cursor.fetchall():
                record = self.records.get(row['information_object_id'])
                if not record:
                    continue
                
                # Skip if we already have GRAP data for this record
                existing = [g for g in self.grap_assets.values() 
                           if g.get('grap:relatedRecord', {}).get('@id') == record['@id']]
                if existing:
                    continue
                
                grap_id = f"grap_spectrum_{row['id']}"
                
                grap_asset = {
                    '@id': self.mint_uri('grap-asset', grap_id),
                    '@type': ['grap:HeritageAsset'],
                    'grap:relatedRecord': {'@id': record['@id']},
                    'grap:recognitionStatus': row['recognition_status'],
                    'grap:measurementBasis': row['measurement_basis'],
                    'grap:assetClass': row['asset_class'],
                }
                
                if row['initial_recognition_date']:
                    grap_asset['grap:recognitionDate'] = str(row['initial_recognition_date'])
                if row['initial_recognition_value']:
                    grap_asset['grap:initialRecognitionValue'] = float(row['initial_recognition_value'])
                if row['carrying_amount']:
                    grap_asset['grap:currentCarryingAmount'] = float(row['carrying_amount'])
                if row['heritage_significance_rating']:
                    grap_asset['grap:heritageSignificance'] = row['heritage_significance_rating']
                
                # Clean up
                grap_asset = {k: v for k, v in grap_asset.items() if v is not None}
                
                self.grap_assets[f"spectrum_{row['id']}"] = grap_asset
                
                self.relations.append({
                    'from': record['@id'],
                    'to': grap_asset['@id'],
                    'predicate': 'grap:hasHeritageAssetData',
                })
                
        except Exception as e:
            print(f"Warning: Could not extract spectrum_grap_data: {e}")
    
    def _build_jsonld(self) -> Dict:
        graph = []
        
        # Build relation index
        relation_map = defaultdict(list)
        for rel in self.relations:
            relation_map[rel['from']].append(rel)
        
        all_predicates = [
            'rico:hasOrHadSubject', 'rico:hasOrHadMainSubject',
            'rico:hasOrHadPlaceOfOrigin', 'rico:hasOrHadContentOfType',
            'rico:hasInstantiation', 'rico:isAssociatedWith',
            'rico:isOrWasRegulatedBy', 'rico:isDescribedBy',
            'rico:hasCreator', 'rico:hasAccumulator',
            'rico:precedes', 'rico:follows', 'rico:includes',
            'rico:hasOrHadFunction', 'rico:isEquivalentTo',
            'grap:hasHeritageAssetData',
        ]
        
        # Add records
        for record_id, record in self.records.items():
            record_clean = {k: v for k, v in record.items() if v is not None and not k.startswith('_')}
            
            if self.repository:
                record_clean['rico:isOrWasHeldBy'] = {'@id': self.repository['@id']}
            
            record_relations = relation_map.get(record_clean.get('@id'), [])
            for pred in all_predicates:
                targets = [r['to'] for r in record_relations if r['predicate'] == pred]
                if targets:
                    targets = list(dict.fromkeys(targets))
                    if len(targets) == 1:
                        record_clean[pred] = {'@id': targets[0]}
                    else:
                        record_clean[pred] = [{'@id': t} for t in targets]
                
            graph.append(record_clean)
        
        # Add agents
        for agent in self.agents.values():
            agent_clean = {k: v for k, v in agent.items() if v is not None and not k.startswith('_')}
            agent_relations = relation_map.get(agent_clean.get('@id'), [])
            for pred in all_predicates:
                targets = [r['to'] for r in agent_relations if r['predicate'] == pred]
                if targets:
                    targets = list(dict.fromkeys(targets))
                    if len(targets) == 1:
                        agent_clean[pred] = {'@id': targets[0]}
                    else:
                        agent_clean[pred] = [{'@id': t} for t in targets]
            graph.append(agent_clean)
        
        # Add core activities
        for activity in self.activities.values():
            act_clean = {k: v for k, v in activity.items() if not k.startswith('_')}
            graph.append(act_clean)
        
        # Add other entities
        for place in self.places.values():
            graph.append({k: v for k, v in place.items() if not k.startswith('_')})
        
        for subject in self.subjects.values():
            graph.append({k: v for k, v in subject.items() if not k.startswith('_')})
        
        for genre in self.genres.values():
            graph.append({k: v for k, v in genre.items() if not k.startswith('_')})
        
        for instantiation in self.instantiations.values():
            graph.append(instantiation)
        
        for rule in self.rules.values():
            graph.append({k: v for k, v in rule.items() if v is not None})
        
        for mandate in self.mandates.values():
            graph.append(mandate)
        
        for function in self.functions.values():
            graph.append({k: v for k, v in function.items() if v is not None})
        
        if self.repository:
            graph.append({k: v for k, v in self.repository.items() if v is not None and not k.startswith('_')})
        
        # Phase 5: Add Spectrum/GRAP entities
        for condition in self.condition_checks.values():
            graph.append({k: v for k, v in condition.items() if v is not None})
        
        for valuation in self.valuations.values():
            graph.append({k: v for k, v in valuation.items() if v is not None})
        
        for loan in self.loans_out.values():
            graph.append({k: v for k, v in loan.items() if v is not None})
        
        for movement in self.movements.values():
            graph.append({k: v for k, v in movement.items() if v is not None})
        
        for grap_asset in self.grap_assets.values():
            graph.append(grap_asset)
        
        # Count relations by type
        relation_counts = defaultdict(int)
        for rel in self.relations:
            relation_counts[rel['predicate']] += 1
        
        return {
            '@context': {
                'rico': 'https://www.ica.org/standards/RiC/ontology#',
                'rdf': 'http://www.w3.org/1999/02/22-rdf-syntax-ns#',
                'rdfs': 'http://www.w3.org/2000/01/rdf-schema#',
                'xsd': 'http://www.w3.org/2001/XMLSchema#',
                'owl': 'http://www.w3.org/2002/07/owl#',
                'spectrum': 'https://collectionstrust.org.uk/spectrum#',
                'grap': 'https://www.asb.co.za/grap#',
            },
            '@graph': graph,
            '_metadata': {
                'extracted': datetime.utcnow().isoformat() + 'Z',
                'source': f'AtoM instance: {self.instance_id}',
                'extractor_version': '5.0',
                'records_count': len(self.records),
                'agents_count': len(self.agents),
                'activities_count': len(self.activities),
                'places_count': len(self.places),
                'subjects_count': len(self.subjects),
                'genres_count': len(self.genres),
                'instantiations_count': len(self.instantiations),
                'rules_count': len(self.rules),
                'mandates_count': len(self.mandates),
                'functions_count': len(self.functions),
                'condition_checks_count': len(self.condition_checks),
                'valuations_count': len(self.valuations),
                'loans_out_count': len(self.loans_out),
                'movements_count': len(self.movements),
                'grap_assets_count': len(self.grap_assets),
                'relations_count': len(self.relations),
                'relation_types': dict(relation_counts),
            }
        }


def main():
    parser = argparse.ArgumentParser(description='Extract AtoM data to RiC-O JSON-LD (v5 - Spectrum/GRAP)')
    parser.add_argument('--list-fonds', action='store_true', help='List available fonds')
    parser.add_argument('--fonds-id', type=int, help='ID of fonds to extract')
    parser.add_argument('--output', '-o', type=str, default='output.jsonld', help='Output file')
    parser.add_argument('--pretty', action='store_true', help='Pretty-print JSON')
    parser.add_argument('--list-standalone', action='store_true', help='List standalone records (non-fonds)')
    
    args = parser.parse_args()
    
    db_config = {
        'host': os.environ.get('ATOM_DB_HOST', 'localhost'),
        'user': os.environ.get('ATOM_DB_USER', 'root'),
        'password': os.environ.get('ATOM_DB_PASSWORD', 'Merlot@123'),
        'database': os.environ.get('ATOM_DB_NAME', 'archive'),
    }
    
    base_uri = os.environ.get('RIC_BASE_URI', 'https://archives.theahg.co.za/ric')
    instance_id = os.environ.get('ATOM_INSTANCE_ID', 'atom-psis')
    
    extractor = RiCExtractor(db_config, base_uri, instance_id)
    
    try:
        extractor.connect()
        
        if args.list_fonds:
            fonds_list = extractor.list_fonds()
            print("\nAvailable fonds:\n")
            print(f"{'ID':<8} {'Identifier':<20} {'Title':<50} {'Descendants':<10}")
            print("-" * 90)
            for f in fonds_list:
                print(f"{f['id']:<8} {(f['identifier'] or ''):<20} {(f['title'] or '')[:48]:<50} {f['descendant_count']:<10}")

        elif args.list_standalone:
            standalone_list = extractor.list_standalone()
            print("\nStandalone records (non-fonds):\n")
            print(f"{'ID':<8} {'Identifier':<15} {'Level':<20} {'Title':<45} {'Desc':<6}")
            print("-" * 95)
            for r in standalone_list:
                print(f"{r['id']:<8} {(r['identifier'] or '')[:13]:<15} {(r['level'] or '')[:18]:<20} {(r['title'] or '')[:43]:<45} {r['descendant_count']}") 
                      
        elif args.fonds_id:
            print(f"\nExtracting fonds ID: {args.fonds_id}")
            result = extractor.extract_fonds(args.fonds_id)
            
            indent = 2 if args.pretty else None
            with open(args.output, 'w', encoding='utf-8') as f:
                json.dump(result, f, indent=indent, ensure_ascii=False, cls=DecimalEncoder)
            
            meta = result['_metadata']
            print(f"\n{'='*60}")
            print(f"Extraction complete (v5 - Spectrum/GRAP)")
            print(f"{'='*60}")
            print(f"\nCore Entities:")
            print(f"  Records: {meta['records_count']}")
            print(f"  Agents: {meta['agents_count']}")
            print(f"  Activities: {meta['activities_count']}")
            print(f"  Places: {meta['places_count']}")
            print(f"  Subjects: {meta['subjects_count']}")
            print(f"  Genres: {meta['genres_count']}")
            print(f"  Instantiations: {meta['instantiations_count']}")
            print(f"  Rules: {meta['rules_count']}")
            print(f"  Mandates: {meta['mandates_count']}")
            print(f"  Functions: {meta['functions_count']}")
            print(f"\nSpectrum/GRAP Extensions:")
            print(f"  Condition Checks: {meta['condition_checks_count']}")
            print(f"  Valuations: {meta['valuations_count']}")
            print(f"  Loans Out: {meta['loans_out_count']}")
            print(f"  Movements: {meta['movements_count']}")
            print(f"  GRAP Assets: {meta['grap_assets_count']}")
            print(f"\nTotal Relations: {meta['relations_count']}")
            print(f"\nRelation Types:")
            for rel_type, count in sorted(meta['relation_types'].items()):
                print(f"  {rel_type}: {count}")
            print(f"\nOutput: {args.output}")
            print(f"{'='*60}")
            
        else:
            parser.print_help()
            
    finally:
        extractor.close()


if __name__ == '__main__':
    main()
