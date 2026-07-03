import csv
from openpyxl import load_workbook

# zip local-file-header signature -> real proof a file is an xlsx (it's a
# zip container). We check this instead of trusting the stored extension.
XLSX_MAGIC = b"PK\x03\x04"


def sniff_is_xlsx(path):
    with open(path, "rb") as f:
        header = f.read(4)
    return header == XLSX_MAGIC


def read_csv(path):
    rows = []
    # newline="" -> let csv handle line endings itself (required, avoids bugs)
    # encoding="utf-8-sig" -> strip Excel's invisible BOM marker if present
    with open(path, newline="", encoding="utf-8-sig") as f:
        reader = csv.DictReader(f)
        for row in reader:
            rows.append(row)
    return rows


def read_xlsx(path):
    # read_only=True streams rows instead of loading the whole sheet into
    # memory at once -> keeps large files from ballooning RAM usage.
    workbook = load_workbook(path, read_only=True, data_only=True)
    sheet = workbook.active

    rows_iter = sheet.iter_rows(values_only=True)
    header = next(rows_iter, None)
    if header is None:
        return []

    rows = []
    for values in rows_iter:
        # openpyxl gives typed cells (int, float, datetime, None) -- but
        # validator.py/customers.py call .strip() expecting strings, same
        # as csv.DictReader produces. Normalize every value to a string.
        row = {
            key: ("" if value is None else str(value))
            for key, value in zip(header, values)
        }
        rows.append(row)

    workbook.close()
    return rows


def read_customers_file(path):
    """Dispatch by real file content (magic bytes), not by file extension."""
    if sniff_is_xlsx(path):
        return read_xlsx(path)
    return read_csv(path)
