import io
import shutil
import subprocess
import sys
import tarfile
import tempfile
import zipfile
from pathlib import Path

root = Path(__file__).resolve().parent.parent
output = Path(sys.argv[1]).resolve()
vendor = root / 'assets' / 'vendor'
for required in ('pdf-lib/pdf-lib.min.js', 'pdfjs/build/pdf.js', 'pdfjs/build/pdf.worker.js', 'versions.json'):
    if not (vendor / required).is_file():
        raise RuntimeError('Run node scripts/build-pdf-assets.mjs before packaging: ' + required)
package = Path(tempfile.mkdtemp(prefix='portal-rh-package-'))
archive = subprocess.run(['git', '-C', str(root), 'archive', '--format=tar', '--prefix=portal-rh/', 'HEAD'], check=True, capture_output=True).stdout
with tarfile.open(fileobj=io.BytesIO(archive)) as source:
    source.extractall(package, filter='data')
shutil.copytree(vendor, package / 'portal-rh' / 'assets' / 'vendor', dirs_exist_ok=True)
output.parent.mkdir(parents=True, exist_ok=True)
with zipfile.ZipFile(output, 'w', compression=zipfile.ZIP_DEFLATED, compresslevel=9) as bundle:
    for file in sorted(package.rglob('*')):
        if file.is_file():
            bundle.write(file, file.relative_to(package).as_posix())
print(output)
