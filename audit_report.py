#!/usr/bin/env python3
import subprocess, json, sys

REPO = "ArchiveHeritageGroup/heratio"

def gh_graphql(query):
    result = subprocess.run(
        ["gh", "api", "graphql", "-f", f"query={query}"],
        capture_output=True, text=True
    )
    if result.returncode != 0:
        print("GH error:", result.stderr, file=sys.stderr)
        return None
    return json.loads(result.stdout)

cursor = None
total = 0
open_audit = closed_audit = open_other = closed_other = 0

for page in range(1, 20):
    if cursor:
        query = """
        { repository(owner: "ArchiveHeritageGroup", name: "heratio") {
            issues(first: 100, after: "%s") {
              pageInfo { hasNextPage endCursor }
              totalCount
              nodes { number state labels(first:10) { nodes { name } } }
            }
          }
        }
        """ % cursor
    else:
        query = """
        { repository(owner: "ArchiveHeritageGroup", name: "heratio") {
            issues(first: 100) {
              pageInfo { hasNextPage endCursor }
              totalCount
              nodes { number state labels(first:10) { nodes { name } } }
            }
          }
        }
        """

    result = gh_graphql(query)
    if not result:
        break

    data = result["data"]["repository"]["issues"]
    nodes = data["nodes"]
    page_info = data["pageInfo"]

    if page == 1:
        total = data["totalCount"]
        print(f"Total issues on repo: {total}", file=sys.stderr)

    for issue in nodes:
        labels = [l["name"] for l in issue["labels"]["nodes"]]
        is_audit = "audit" in labels
        is_open = issue["state"] == "OPEN"
        if is_audit:
            if is_open: open_audit += 1
            else: closed_audit += 1
        else:
            if is_open: open_other += 1
            else: closed_other += 1

    print(f"Page {page}: {len(nodes)} issues", file=sys.stderr)

    if not page_info["hasNextPage"]:
        break
    cursor = page_info["endCursor"]

scanned = open_audit + closed_audit + open_other + closed_other
print()
print(f"=== ISSUE BREAKDOWN (scanned {scanned} of {total} total) ===")
print(f"  [audit] label — Open: {open_audit} | Closed: {closed_audit}")
print(f"  Other issues  — Open: {open_other} | Closed: {closed_other}")
print(f"  TOTAL open: {open_audit + open_other} | TOTAL closed: {closed_audit + closed_other}")

# Print open non-audit issues with titles
if open_other > 0:
    print()
    print("=== OPEN NON-AUDIT ISSUES ===")
    cursor = None
    for _ in range(1, 20):
        if cursor:
            q = """
            { repository(owner: "ArchiveHeritageGroup", name: "heratio") {
                issues(first: 100, after: "%s", filterBy: {states: OPEN}) {
                  pageInfo { hasNextPage endCursor }
                  nodes { number title labels(first:10) { nodes { name } } }
                }
              }
            }
            """ % cursor
        else:
            q = """
            { repository(owner: "ArchiveHeritageGroup", name: "heratio") {
                issues(first: 100, filterBy: {states: OPEN}) {
                  pageInfo { hasNextPage endCursor }
                  nodes { number title labels(first:10) { nodes { name } } }
                }
              }
            }
            """
        result = gh_graphql(q)
        if not result:
            break
        nodes = result["data"]["repository"]["issues"]["nodes"]
        page_info = result["data"]["repository"]["issues"]["pageInfo"]
        for issue in nodes:
            labels = [l["name"] for l in issue["labels"]["nodes"]]
            if "audit" not in labels:
                print(f"  #{issue['number']} [{', '.join(labels)}] {issue['title']}")
        if not page_info["hasNextPage"]:
            break
        cursor = page_info["endCursor"]