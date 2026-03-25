#!/bin/bash
# =============================================================================
# Fuseki Optimization Script for Large Datasets (14M+ triples)
# =============================================================================
# This script optimizes Apache Jena Fuseki for better performance with large
# RDF datasets. Run with sudo.
# =============================================================================

set -e

FUSEKI_CONTAINER="fuseki"
FUSEKI_DATA_DIR="/var/lib/fuseki"  # Adjust if your data is elsewhere

echo "=============================================="
echo "Fuseki Performance Optimization Script"
echo "=============================================="
echo ""

# Check if running as root
if [[ $EUID -ne 0 ]]; then
   echo "This script should be run with sudo for Docker operations"
   echo "Usage: sudo $0"
   exit 1
fi

# Check Docker
if ! command -v docker &> /dev/null; then
    echo "Docker not found. Is Fuseki running in Docker?"
    exit 1
fi

# Get current container info
echo "1. Checking current Fuseki container..."
if docker ps | grep -q $FUSEKI_CONTAINER; then
    echo "   Container '$FUSEKI_CONTAINER' is running"
    CURRENT_MEM=$(docker inspect $FUSEKI_CONTAINER --format '{{.HostConfig.Memory}}' 2>/dev/null || echo "0")
    echo "   Current memory limit: $CURRENT_MEM bytes (0 = unlimited)"
else
    echo "   Container '$FUSEKI_CONTAINER' not found"
    exit 1
fi

echo ""
echo "2. Current JVM settings:"
docker exec $FUSEKI_CONTAINER ps aux | grep java | head -1 || true

echo ""
echo "=============================================="
echo "RECOMMENDED OPTIMIZATIONS"
echo "=============================================="
echo ""
echo "A) INCREASE JVM MEMORY (Currently 4GB -> 16GB recommended)"
echo "   Stop the container and restart with more memory:"
echo ""
echo "   docker stop $FUSEKI_CONTAINER"
echo "   docker run -d --name $FUSEKI_CONTAINER -p 3030:3030 \\"
echo "     -e JVM_ARGS=\"-Xmx16G -XX:+UseG1GC\" \\"
echo "     -v /path/to/fuseki-data:/fuseki/databases \\"
echo "     stain/jena-fuseki"
echo ""

echo "B) RUN TDB2 STATISTICS (Improves query planning)"
echo "   This analyzes your data and creates statistics for better query optimization."
echo ""
echo "   docker exec $FUSEKI_CONTAINER /jena-fuseki/tdb2.tdbstats --loc=/fuseki/databases/ric"
echo ""

echo "C) ADD QUERY TIMEOUT (Prevent runaway queries)"
echo "   Add this to your Fuseki configuration file:"
echo ""
echo '   ja:context [ ja:cxtName "arq:queryTimeout" ; ja:cxtValue "30000" ] ;'
echo ""

echo "D) COMPACT TDB2 DATABASE (Reclaim space, improve read performance)"
echo ""
echo "   docker exec $FUSEKI_CONTAINER /jena-fuseki/tdb2.tdbcompact --loc=/fuseki/databases/ric"
echo ""

echo "=============================================="
echo "AUTOMATIC ACTIONS"
echo "=============================================="

read -p "Run TDB2 statistics now? (y/N) " -n 1 -r
echo
if [[ $REPLY =~ ^[Yy]$ ]]; then
    echo "Running TDB2 statistics..."
    docker exec $FUSEKI_CONTAINER /jena-fuseki/bin/tdb2.tdbstats --loc=/fuseki/databases/ric 2>/dev/null || \
    docker exec $FUSEKI_CONTAINER java -cp "/jena-fuseki/lib/*" tdb2.tdbstats --loc=/fuseki/databases/ric 2>/dev/null || \
    echo "Could not run tdbstats - you may need to run it manually"
fi

read -p "Compact TDB2 database now? (y/N) " -n 1 -r
echo
if [[ $REPLY =~ ^[Yy]$ ]]; then
    echo "Running TDB2 compact (this may take a while for large datasets)..."
    docker exec $FUSEKI_CONTAINER /jena-fuseki/bin/tdb2.tdbcompact --loc=/fuseki/databases/ric 2>/dev/null || \
    docker exec $FUSEKI_CONTAINER java -cp "/jena-fuseki/lib/*" tdb2.tdbcompact --loc=/fuseki/databases/ric 2>/dev/null || \
    echo "Could not run tdbcompact - you may need to run it manually"
fi

echo ""
echo "=============================================="
echo "Done! For the memory increase, you'll need to"
echo "manually stop and restart the container."
echo "=============================================="
