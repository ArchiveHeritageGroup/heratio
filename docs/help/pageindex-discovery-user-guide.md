> Heratio Help Center article. Category: Browse & Search.

# PageIndex Discovery — LLM-Powered Search

## What is PageIndex?

PageIndex is a vectorless, reasoning-based retrieval system that uses a local LLM (Large Language Model) to understand and search your archival records. Unlike traditional keyword search or vector embeddings, PageIndex builds a hierarchical "table of contents" (tree index) for each document, then uses LLM reasoning to find the most relevant sections for your query.

## How It Works

PageIndex uses a two-step process:

### Step 1: Tree Construction

When you index a record, PageIndex:
1. Extracts content from the record (EAD metadata, PDF text, or RiC-O triples)
2. Sends the content to a local Ollama LLM (llama3.1:8b)
3. The LLM analyses the document structure and returns a hierarchical JSON tree
4. Each node in the tree has a title, summary, keywords, and child nodes
5. The tree is stored in the database for future queries

### Step 2: Retrieval Reasoning

When you search:
1. Your query is sent to the LLM along with the document tree
2. The LLM reasons about which nodes are relevant to your query
3. It returns ranked matches with relevance scores and explanations
4. Results include breadcrumb paths showing exactly where in the document hierarchy the match was found

## Supported Document Types

| Type | Source | Description |
|------|--------|-------------|
| EAD | MySQL | Finding aids from information_object tables (ISAD(G) hierarchy) |
| PDF | OCR Service | Uploaded PDF digital objects, text extracted via OCR |
| RiC-O | Fuseki SPARQL | Records in Contexts metadata from the triplestore |

## Using PageIndex Search

1. Navigate to **Discovery > PageIndex** from the main menu
2. Enter a natural language query (e.g., "correspondence about land transfers in the 1960s")
3. Optionally filter by document type (EAD, PDF, RiC-O)
4. Click **Search** and wait for the LLM to reason through the indexed trees

### Understanding Results

Each result shows:
- **Record title** with a link to the full record
- **Type badge** indicating whether the match is from an EAD finding aid, PDF, or RiC-O metadata
- **Breadcrumb path** showing the hierarchy: Fonds > Series > Sub-series > File
- **Node summary** explaining what the matched section contains
- **Relevance score** from 0-100%
- **Match reason** explaining why the LLM considers this section relevant
- **Overall reasoning** at the top summarizing the search strategy

### Key Feature: Explainability

The breadcrumb path is the key differentiator. Unlike keyword search which just returns "this record matched", PageIndex tells you exactly *where* in the hierarchy the relevant content is and *why* it matches your query.

## Indexing Records

### Admin Trigger

On any information object show page, administrators can trigger indexing:

1. Navigate to the record you want to index
2. Click the **Build PageIndex** button
3. Select the document type (EAD for archival descriptions, PDF for attached documents)
4. Wait for the LLM to build the tree (typically 10-30 seconds)
5. The status will show: **pending** > **building** > **ready**

### Index Status

Each indexed record shows:
- **Status**: pending, building, ready, or error
- **Indexed at**: When the tree was last built
- **Model used**: The LLM model (e.g., llama3.1:8b)
- **Node count**: Number of sections in the tree

## Technical Details

- **LLM**: Ollama running locally at 192.168.0.112:11434
- **Model**: llama3.1:8b (configurable)
- **No external API calls**: All processing happens on the local network
- **No vector database**: No Qdrant, no embeddings — pure reasoning
- **Storage**: Trees stored as JSON in the ahg_pageindex_tree table
- **Query logging**: All queries logged to ahg_pageindex_query_log for analytics

## Comparison with Other Search Modes

| Feature | Standard | Semantic | Vector | PageIndex |
|---------|----------|----------|--------|-----------|
| Speed | Fast | Medium | Medium | Slower (LLM reasoning) |
| Accuracy | Keywords only | + NER entities | + vector similarity | + structural reasoning |
| Explainability | None | Entity tags | Similarity % | Full breadcrumb + reasoning |
| Setup | None | NER service | Qdrant + embeddings | Ollama + indexing |
| Best for | Known terms | Entity relationships | Similar content | Understanding structure |
