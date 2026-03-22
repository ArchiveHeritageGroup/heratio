import datetime
from typing import Dict, List


def _gedcom_date(raw):
    if not raw or not raw.strip():
        return ""
    raw = raw.strip()
    months = ["JAN", "FEB", "MAR", "APR", "MAY", "JUN",
              "JUL", "AUG", "SEP", "OCT", "NOV", "DEC"]
    for fmt in ("%Y-%m-%d", "%d/%m/%Y", "%d-%m-%Y", "%Y/%m/%d", "%d %B %Y", "%d %b %Y"):
        try:
            dt = datetime.datetime.strptime(raw, fmt)
            return f"{dt.day} {months[dt.month - 1]} {dt.year}"
        except ValueError:
            continue
    return raw


def _line(level, tag, value=""):
    return f"{level} {tag} {value}" if value else f"{level} {tag}"


def _get(fields, name):
    for f in fields:
        if f.get("name", "").lower() == name.lower():
            return f.get("value", "").strip()
    return ""


def birth_to_gedcom(fields, idx=1):
    lines = []
    xref = f"@I{idx}@"
    sn = _get(fields, "surname")
    fn = _get(fields, "first_names")
    dob = _get(fields, "date_of_birth")
    pob = _get(fields, "place_of_birth")
    father = _get(fields, "father_name")
    mother = _get(fields, "mother_name")
    reg = _get(fields, "registration_number")
    lines.append(_line(0, xref, "INDI"))
    lines.append(_line(1, "NAME", f"{fn} /{sn}/"))
    lines.append(_line(2, "GIVN", fn))
    lines.append(_line(2, "SURN", sn))
    lines.append(_line(1, "SEX", "U"))
    lines.append(_line(1, "BIRT"))
    if dob:
        lines.append(_line(2, "DATE", _gedcom_date(dob)))
    if pob:
        lines.append(_line(2, "PLAC", pob))
    if reg:
        lines.append(_line(1, "REFN", reg))
        lines.append(_line(2, "TYPE", "Registration Number"))
    if father:
        fx = f"@I{idx + 100}@"
        p = father.rsplit(" ", 1)
        fg = p[0] if len(p) > 1 else father
        fs = p[-1] if len(p) > 1 else ""
        lines += ["", _line(0, fx, "INDI"), _line(1, "NAME", f"{fg} /{fs}/"), _line(2, "GIVN", fg)]
        if fs:
            lines.append(_line(2, "SURN", fs))
        lines.append(_line(1, "SEX", "M"))
    if mother:
        mx = f"@I{idx + 200}@"
        p = mother.rsplit(" ", 1)
        mg = p[0] if len(p) > 1 else mother
        ms = p[-1] if len(p) > 1 else ""
        lines += ["", _line(0, mx, "INDI"), _line(1, "NAME", f"{mg} /{ms}/"), _line(2, "GIVN", mg)]
        if ms:
            lines.append(_line(2, "SURN", ms))
        lines.append(_line(1, "SEX", "F"))
    if father or mother:
        fam = f"@F{idx}@"
        lines += ["", _line(0, fam, "FAM")]
        if father:
            lines.append(_line(1, "HUSB", f"@I{idx + 100}@"))
        if mother:
            lines.append(_line(1, "WIFE", f"@I{idx + 200}@"))
        lines.append(_line(1, "CHIL", xref))
    return lines


def death_to_gedcom(fields, idx=1):
    lines = []
    xref = f"@I{idx}@"
    sn = _get(fields, "surname")
    fn = _get(fields, "first_names")
    lines.append(_line(0, xref, "INDI"))
    lines.append(_line(1, "NAME", f"{fn} /{sn}/"))
    lines.append(_line(2, "GIVN", fn))
    lines.append(_line(2, "SURN", sn))
    lines.append(_line(1, "SEX", "U"))
    lines.append(_line(1, "DEAT"))
    dod = _get(fields, "date_of_death")
    if dod:
        lines.append(_line(2, "DATE", _gedcom_date(dod)))
    pod = _get(fields, "place_of_death")
    if pod:
        lines.append(_line(2, "PLAC", pod))
    cause = _get(fields, "cause_of_death")
    if cause:
        lines.append(_line(2, "CAUS", cause))
    age = _get(fields, "age")
    if age:
        lines.append(_line(1, "NOTE", f"Age at death: {age}"))
    reg = _get(fields, "registration_number")
    if reg:
        lines.append(_line(1, "REFN", reg))
        lines.append(_line(2, "TYPE", "Registration Number"))
    return lines


def marriage_to_gedcom(fields, idx=1):
    lines = []
    gs = _get(fields, "groom_surname")
    gf = _get(fields, "groom_first_names")
    bs = _get(fields, "bride_surname")
    bf = _get(fields, "bride_first_names")
    dom = _get(fields, "date_of_marriage")
    pom = _get(fields, "place_of_marriage")
    w1 = _get(fields, "witness_1")
    w2 = _get(fields, "witness_2")
    reg = _get(fields, "registration_number")
    gx = f"@I{idx}@"
    bx = f"@I{idx + 1}@"
    fx = f"@F{idx}@"
    lines.append(_line(0, gx, "INDI"))
    lines.append(_line(1, "NAME", f"{gf} /{gs}/"))
    lines.append(_line(2, "GIVN", gf))
    lines.append(_line(2, "SURN", gs))
    lines.append(_line(1, "SEX", "M"))
    lines.append(_line(1, "FAMS", fx))
    lines += ["", _line(0, bx, "INDI")]
    lines.append(_line(1, "NAME", f"{bf} /{bs}/"))
    lines.append(_line(2, "GIVN", bf))
    lines.append(_line(2, "SURN", bs))
    lines.append(_line(1, "SEX", "F"))
    lines.append(_line(1, "FAMS", fx))
    lines += ["", _line(0, fx, "FAM"), _line(1, "HUSB", gx), _line(1, "WIFE", bx), _line(1, "MARR")]
    if dom:
        lines.append(_line(2, "DATE", _gedcom_date(dom)))
    if pom:
        lines.append(_line(2, "PLAC", pom))
    if w1:
        lines.append(_line(1, "NOTE", f"Witness 1: {w1}"))
    if w2:
        lines.append(_line(1, "NOTE", f"Witness 2: {w2}"))
    if reg:
        lines.append(_line(1, "REFN", reg))
        lines.append(_line(2, "TYPE", "Registration Number"))
    return lines


def write_gedcom(doc_type, fields, idx=1):
    now = datetime.datetime.now()
    mn = ["JAN", "FEB", "MAR", "APR", "MAY", "JUN",
          "JUL", "AUG", "SEP", "OCT", "NOV", "DEC"]
    ds = f"{now.day} {mn[now.month - 1]} {now.year}"
    header = [
        "0 HEAD", "1 SOUR Heratio-HTR", "2 VERS 1.0",
        "2 NAME Heratio HTR Service", "1 DEST ANY",
        f"1 DATE {ds}", "1 GEDC", "2 VERS 5.5.1",
        "2 FORM LINEAGE-LINKED", "1 CHAR UTF-8",
    ]
    if doc_type == "type_a":
        body = birth_to_gedcom(fields, idx)
    elif doc_type == "type_b":
        body = death_to_gedcom(fields, idx)
    elif doc_type == "type_c":
        body = marriage_to_gedcom(fields, idx)
    else:
        body = [f"1 NOTE Unsupported document type: {doc_type}"]
    return "\n".join(header + [""] + body + ["", "0 TRLR"]) + "\n"
