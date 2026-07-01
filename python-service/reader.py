import csv

def read_csv(path):
    rows = []
    # newline="" -> let csv handle line endings itself (required, avoids bugs)
    # encoding="utf-8-sig" -> strip Excel's invisible BOM marker if present
    with open(path, newline="", encoding="utf-8-sig") as f:
        reader = csv.DictReader(f)
        print(f"CSV header: {reader.fieldnames}")
        for row in reader:
            print(row)                      # whole dict for this row
            print(row["name"], row["email"])  # specific columns
            rows.append(row)
        print(f"total rows read: {len(rows)}")
    return rows
