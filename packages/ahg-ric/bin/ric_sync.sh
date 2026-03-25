#!/bin/bash
#
# RiC Sync Script - Version 2.0
# =============================
#
# Syncs AtoM data to Fuseki triplestore using RiC Extractor v5
# Includes Spectrum/GRAP extensions, validation, and backup
#
# Usage:
#   ./ric_sync.sh                    # Sync all fonds
#   ./ric_sync.sh --fonds 776,829    # Sync specific fonds
#   ./ric_sync.sh --clear            # Clear and resync all
#   ./ric_sync.sh --validate         # Run SHACL validation after sync
#   ./ric_sync.sh --backup           # Backup before sync
#   ./ric_sync.sh --link-authorities # Run authority linking after sync
#   ./ric_sync.sh --cron             # Silent mode for cron
#   ./ric_sync.sh --status           # Show triplestore status
#

set -e

# Configuration
SCRIPT_DIR="$(cd "$(dirname "$(readlink -f "${BASH_SOURCE[0]}")")" && pwd)"
EXTRACTOR="${SCRIPT_DIR}/../tools/ric_extractor_v5.py"
AUTHORITY_LINKER="${SCRIPT_DIR}/../tools/ric_authority_linker.py"
SHACL_VALIDATOR="${SCRIPT_DIR}/../tools/ric_shacl_validator.py"
SHACL_SHAPES="${SCRIPT_DIR}/../tools/ric_shacl_shapes.ttl"

FUSEKI_URL="http://192.168.0.112:3030"
FUSEKI_DATASET="${FUSEKI_DATASET:-ric}"
FUSEKI_USER="${FUSEKI_USER:-admin}"
FUSEKI_PASS="${FUSEKI_PASS:-admin123}"

BACKUP_DIR="${BACKUP_DIR:-/var/backups/fuseki}"
EXTRACT_DIR="${EXTRACT_DIR:-/tmp/ric_extract}"
LOG_FILE="${LOG_FILE:-/var/log/ric_sync.log}"

export ATOM_DB_HOST="${ATOM_DB_HOST:-localhost}"
export ATOM_DB_USER="${ATOM_DB_USER:-root}"
export ATOM_DB_PASSWORD="${ATOM_DB_PASSWORD:-Merlot@123}"
export ATOM_DB_NAME="${ATOM_DB_NAME:-archive}"
export RIC_BASE_URI="${RIC_BASE_URI:-https://archives.theahg.co.za/ric}"
export ATOM_INSTANCE_ID="${ATOM_INSTANCE_ID:-atom-psis}"

# Options
CLEAR_FIRST=false
CRON_MODE=false
VALIDATE=false
BACKUP=false
LINK_AUTHORITIES=false
SPECIFIC_FONDS=""
STATUS_ONLY=false

# Parse arguments
while [[ $# -gt 0 ]]; do
    case $1 in
        --clear) CLEAR_FIRST=true; shift ;;
        --cron) CRON_MODE=true; shift ;;
        --validate) VALIDATE=true; shift ;;
        --backup) BACKUP=true; shift ;;
        --link-authorities) LINK_AUTHORITIES=true; shift ;;
        --fonds) SPECIFIC_FONDS="$2"; shift 2 ;;
        --status) STATUS_ONLY=true; shift ;;
        --help) 
            echo "Usage: $0 [options]"
            echo "Options:"
            echo "  --clear            Clear triplestore before sync"
            echo "  --fonds IDS        Sync specific fonds (comma-separated)"
            echo "  --validate         Run SHACL validation after sync"
            echo "  --backup           Create backup before sync"
            echo "  --link-authorities Run authority linking after sync"
            echo "  --cron             Silent mode for cron jobs"
            echo "  --status           Show triplestore status"
            exit 0
            ;;
        *) echo "Unknown option: $1"; exit 1 ;;
    esac
done

# Logging
log() {
    local msg="[$(date '+%Y-%m-%d %H:%M:%S')] $1"
    if [ "$CRON_MODE" = false ]; then
        echo "$msg"
    fi
    echo "$msg" >> "$LOG_FILE"
}

log_error() {
    local msg="[$(date '+%Y-%m-%d %H:%M:%S')] ERROR: $1"
    echo "$msg" >&2
    echo "$msg" >> "$LOG_FILE"
}

# Check prerequisites
check_prerequisites() {
    if [ ! -f "$EXTRACTOR" ]; then
        log_error "Extractor not found: $EXTRACTOR"
        exit 1
    fi
    
    if ! python3 -c "import mysql.connector" 2>/dev/null; then
        log_error "mysql-connector-python not installed"
        exit 1
    fi
    
    # Check Fuseki is running
    if ! curl -s -o /dev/null -w "%{http_code}" "${FUSEKI_URL}/$/ping" | grep -q "200\|401"; then
        log_error "Fuseki not responding at ${FUSEKI_URL}"
        exit 1
    fi
    
    mkdir -p "$EXTRACT_DIR" "$BACKUP_DIR"
}

# Get triplestore status
get_status() {
    local count=$(curl -s -u "${FUSEKI_USER}:${FUSEKI_PASS}" \
        -X POST "${FUSEKI_URL}/$/ping" \
        -H "Content-Type: application/sparql-query" \
        -d "SELECT (COUNT(*) as ?count) WHERE { ?s ?p ?o }" \
        | grep -oP '"value"\s*:\s*"\K[0-9]+' | head -1)
    echo "${count:-0}"
}

# Show status
show_status() {
    log "=========================================="
    log "RiC Triplestore Status"
    log "=========================================="
    
    local count=$(get_status)
    log "Total triples: $count"
    
    # Count by type
    local query='
PREFIX rico: <https://www.ica.org/standards/RiC/ontology#>
SELECT ?type (COUNT(?s) as ?count) WHERE {
    ?s a ?type .
    FILTER(STRSTARTS(STR(?type), "https://www.ica.org"))
}
GROUP BY ?type
ORDER BY DESC(?count)
LIMIT 15'
    
    log ""
    log "Entities by type:"
    curl -s -u "${FUSEKI_USER}:${FUSEKI_PASS}" \
        -X POST "${FUSEKI_URL}/$/ping" \
        -H "Content-Type: application/sparql-query" \
        -H "Accept: text/csv" \
        -d "$query" | tail -n +2 | while IFS=, read type count; do
            type_name=$(echo "$type" | sed 's/.*#//' | tr -d '"')
            count_clean=$(echo "$count" | tr -d '"')
            log "  $type_name: $count_clean"
        done
    
    # Count Spectrum/GRAP activities
    local spectrum_query='
PREFIX rico: <https://www.ica.org/standards/RiC/ontology#>
SELECT ?actType (COUNT(*) as ?count) WHERE {
    ?s a rico:Activity ;
       rico:hasActivityType ?actType .
}
GROUP BY ?actType'
    
    log ""
    log "Activity types:"
    curl -s -u "${FUSEKI_USER}:${FUSEKI_PASS}" \
        -X POST "${FUSEKI_URL}/$/ping" \
        -H "Content-Type: application/sparql-query" \
        -H "Accept: text/csv" \
        -d "$spectrum_query" | tail -n +2 | while IFS=, read type count; do
            type_clean=$(echo "$type" | tr -d '"')
            count_clean=$(echo "$count" | tr -d '"')
            log "  $type_clean: $count_clean"
        done
    
    log "=========================================="
}

# Backup triplestore
backup_triplestore() {
    log "Creating backup..."
    
    local backup_file="${BACKUP_DIR}/ric_backup_$(date '+%Y%m%d_%H%M%S').nq"
    
    curl -s -u "${FUSEKI_USER}:${FUSEKI_PASS}" \
        "${FUSEKI_URL}/${FUSEKI_DATASET}/data" \
        -H "Accept: application/n-quads" \
        -o "$backup_file"
    
    if [ -s "$backup_file" ]; then
        gzip "$backup_file"
        log "Backup saved: ${backup_file}.gz"
        
        # Keep only last 7 backups
        ls -t "${BACKUP_DIR}"/ric_backup_*.nq.gz 2>/dev/null | tail -n +8 | xargs -r rm
    else
        log_error "Backup failed - empty file"
        rm -f "$backup_file"
    fi
}

# Clear triplestore
clear_triplestore() {
    log "Clearing triplestore..."
    
    curl -s -u "${FUSEKI_USER}:${FUSEKI_PASS}" \
        -X POST "${FUSEKI_URL}/${FUSEKI_DATASET}/update" \
        -H "Content-Type: application/sparql-update" \
        -d "CLEAR ALL"
    
    log "Triplestore cleared"
}

# Get list of fonds
get_fonds_list() {
    python3 "$EXTRACTOR" --list-fonds 2>/dev/null | grep -E '^\s*[0-9]+' | awk '{print $1}'
}

get_standalone_list() {
    python3 "$EXTRACTOR" --list-standalone 2>/dev/null | grep -E "^\s*[0-9]+" | awk "{print \$1}"
}

# Extract and load a fonds
extract_fonds() {
    local fonds_id=$1
    local output_file="${EXTRACT_DIR}/fonds_${fonds_id}.jsonld"
    
    log "Extracting fonds $fonds_id..."
    
    # Extract to JSON-LD
    if ! python3 "$EXTRACTOR" --fonds-id "$fonds_id" --output "$output_file" 2>/dev/null; then
        log_error "Extraction failed for fonds $fonds_id"
        return 1
    fi
    
    if [ ! -s "$output_file" ]; then
        log_error "Empty output for fonds $fonds_id"
        return 1
    fi
    
    log "  Extracted to $output_file"
    
    # Load to Fuseki
    log "  Loading to Fuseki..."
    
    local response=$(curl -s -w "%{http_code}" -o /dev/null \
        -u "${FUSEKI_USER}:${FUSEKI_PASS}" \
        -X POST "${FUSEKI_URL}/${FUSEKI_DATASET}/data" \
        -H "Content-Type: application/ld+json" \
        --data-binary "@${output_file}")
    
    if [ "$response" = "200" ] || [ "$response" = "204" ]; then
        log "  Loaded successfully"
        return 0
    else
        log_error "Load failed for fonds $fonds_id (HTTP $response)"
        return 1
    fi
}

# Run SHACL validation
run_validation() {
    log "Running SHACL validation..."
    
    if [ ! -f "$SHACL_VALIDATOR" ]; then
        log "Validator not found, skipping validation"
        return
    fi
    
    if [ ! -f "$SHACL_SHAPES" ]; then
        log "Shapes file not found, skipping validation"
        return
    fi
    
    python3 "$SHACL_VALIDATOR" --validate --summary --shapes "$SHACL_SHAPES" 2>/dev/null || true
}

# Run authority linking
run_authority_linking() {
    log "Running authority linking..."
    
    if [ ! -f "$AUTHORITY_LINKER" ]; then
        log "Authority linker not found, skipping"
        return
    fi
    
    python3 "$AUTHORITY_LINKER" --check --limit 20 --link 2>/dev/null || true
}

# Main sync process
main() {
    log "=========================================="
    log "RiC Sync Started (v2.0)"
    log "=========================================="
    
    # Status only
    if [ "$STATUS_ONLY" = true ]; then
        show_status
        exit 0
    fi
    
    check_prerequisites
    log "Prerequisites OK"
    
    # Backup if requested
    if [ "$BACKUP" = true ]; then
        backup_triplestore
    fi
    
    # Clear if requested
    if [ "$CLEAR_FIRST" = true ]; then
        clear_triplestore
    fi
    
    # Get fonds to process
    local fonds_list
    if [ -n "$SPECIFIC_FONDS" ]; then
        fonds_list=$(echo "$SPECIFIC_FONDS" | tr ',' ' ')
    else
        fonds_list=$(get_fonds_list)
    fi
    
    local total=$(echo "$fonds_list" | wc -w)
    log "Found $total fonds to process"
    
    local skipped=0
    for fonds_id in $fonds_list; do
        if extract_fonds "$fonds_id"; then
            processed=$((processed + 1))
        else
            skipped=$((skipped + 1))
        fi
    done

    # Process standalone records (non-fonds at top level)
    log "Processing standalone records..."
    local standalone_list
    standalone_list=$(get_standalone_list)
    local standalone_total=$(echo "$standalone_list" | wc -w)
    log "Found $standalone_total standalone records to process"
    local standalone_processed=0
    local standalone_skipped=0
    for record_id in $standalone_list; do
        if extract_fonds "$record_id"; then
            standalone_processed=$((standalone_processed + 1))
        else
            standalone_skipped=$((standalone_skipped + 1))
        fi
    done
    processed=$((processed + standalone_processed))
    skipped=$((skipped + standalone_skipped))

    # Post-sync tasks
    if [ "$VALIDATE" = true ]; then
        run_validation
    fi

    if [ "$LINK_AUTHORITIES" = true ]; then
        run_authority_linking
    fi

    # Final status
    local final_count=$(get_status)
    log "=========================================="
    log "RiC Sync Complete"
    log "  Processed: $processed records ($standalone_processed standalone)"
    log "  Skipped: $skipped records"
    log "  Total triples: $final_count"
    log "=========================================="
}

# Run
main
