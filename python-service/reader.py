import csv
import zipfile
from openpyxl import load_workbook

# zip local-file-header signature -> real proof a file is an xlsx (it's a
# zip container). We check this instead of trusting the stored extension.
XLSX_MAGIC = b"PK\x03\x04"

# xlsx is a zip container: the 10MB-on-disk limit (StoreImportRequest.php)
# says nothing about how big it gets once unzipped -- a small, highly
# compressible file can still decompress into gigabytes ("zip bomb") and
# exhaust python-service's memory when openpyxl reads it. Reject before
# that happens by inspecting the zip's own directory listing first, which
# is cheap to read and never decompresses entry content.
MAX_XLSX_UNCOMPRESSED_BYTES = 200 * 1024 * 1024  # 200MB decompressed, total
MAX_XLSX_COMPRESSION_RATIO = 100  # normal xlsx sheets run well under this


class UnsafeXlsxError(ValueError):
    pass


def assert_xlsx_safe(path):
    try:
        with zipfile.ZipFile(path) as zf:
            total_uncompressed = 0
            for info in zf.infolist():
                total_uncompressed += info.file_size
                if info.compress_size > 0 and (
                    info.file_size / info.compress_size > MAX_XLSX_COMPRESSION_RATIO
                ):
                    raise UnsafeXlsxError(
                        f"Rejected: entry '{info.filename}' has a suspicious "
                        f"compression ratio"
                    )
            if total_uncompressed > MAX_XLSX_UNCOMPRESSED_BYTES:
                raise UnsafeXlsxError(
                    "Rejected: file would decompress over the size limit"
                )
    except zipfile.BadZipFile:
        raise UnsafeXlsxError("Rejected: not a valid xlsx file")


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
    assert_xlsx_safe(path)

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
