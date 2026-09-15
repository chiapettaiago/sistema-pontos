from pathlib import Path
from zipfile import ZipFile, ZIP_DEFLATED
from xml.etree import ElementTree as ET
import re

base = Path(__file__).parent
ppt = base / 'PontoFacil-Apresentacao-Premium.pptx'
ns = {'p': 'http://schemas.openxmlformats.org/presentationml/2006/main', 'a': 'http://schemas.openxmlformats.org/drawingml/2006/main'}
with ZipFile(ppt) as z:
    contents = {n:z.read(n) for n in z.namelist()}
slides = sorted((n for n in contents if re.fullmatch(r'ppt/slides/slide\d+.xml', n)), key=lambda n:int(re.search(r'slide(\d+)',n).group(1)))
assert len(slides) == 10
for n in slides:
    xml = contents[n].decode()
    root = ET.fromstring(xml)
    candidates = []
    for shape in root.findall('.//p:sp', ns):
        txt = ' '.join(t.text or '' for t in shape.findall('.//a:t', ns))
        sizes = [int(r.get('sz', '0')) for r in shape.findall('.//a:rPr', ns)]
        if txt and sizes:
            candidates.append((max(sizes), shape.find('p:nvSpPr/p:cNvPr', ns).get('id')))
    target = max(candidates)[1]
    transition = '<p:transition spd="med" advClick="1"><p:fade/></p:transition>'
    timing = f'''<p:timing><p:tnLst><p:par><p:cTn id="1" dur="indefinite" restart="never" nodeType="tmRoot"><p:childTnLst><p:seq concurrent="1" nextAc="seek"><p:cTn id="2" dur="indefinite" nodeType="mainSeq"><p:childTnLst><p:par><p:cTn id="3" fill="hold"><p:stCondLst><p:cond delay="0"/></p:stCondLst><p:childTnLst><p:par><p:cTn id="4" fill="hold"><p:stCondLst><p:cond delay="0"/></p:stCondLst><p:childTnLst><p:par><p:cTn id="5" presetID="10" presetClass="entr" presetSubtype="0" fill="hold" nodeType="withEffect"><p:stCondLst><p:cond delay="0"/></p:stCondLst><p:childTnLst><p:set><p:cBhvr><p:cTn id="6" dur="1" fill="hold"><p:stCondLst><p:cond delay="0"/></p:stCondLst></p:cTn><p:tgtEl><p:spTgt spid="{target}"/></p:tgtEl><p:attrNameLst><p:attrName>style.visibility</p:attrName></p:attrNameLst></p:cBhvr><p:to><p:strVal val="visible"/></p:to></p:set><p:animEffect transition="in" filter="fade"><p:cBhvr><p:cTn id="7" dur="500"/><p:tgtEl><p:spTgt spid="{target}"/></p:tgtEl></p:cBhvr></p:animEffect></p:childTnLst></p:cTn></p:par></p:childTnLst></p:cTn></p:par></p:childTnLst></p:cTn></p:par></p:childTnLst></p:cTn><p:prevCondLst><p:cond evt="onPrev" delay="0"><p:tgtEl><p:sldTgt/></p:tgtEl></p:cond></p:prevCondLst><p:nextCondLst><p:cond evt="onNext" delay="0"><p:tgtEl><p:sldTgt/></p:tgtEl></p:cond></p:nextCondLst></p:seq></p:childTnLst></p:cTn></p:par></p:tnLst></p:timing>'''
    xml = xml.replace('</p:sld>', transition + timing + '</p:sld>')
    ET.fromstring(xml)
    contents[n] = xml.encode()
with ZipFile(ppt, 'w', ZIP_DEFLATED) as z:
    for n,b in contents.items(): z.writestr(n,b)
with ZipFile(ppt) as z:
    assert z.testzip() is None
    for n in z.namelist():
        if n.endswith('.xml') or n.endswith('.rels'): ET.fromstring(z.read(n))
    for n in slides:
        root = ET.fromstring(z.read(n))
        assert root.find('p:transition/p:fade',ns) is not None
        assert root.find('.//p:animEffect',ns) is not None
        ids = {el.get('id') for el in root.findall('.//p:cNvPr',ns)}
        assert all(t.get('spid') in ids for t in root.findall('.//p:spTgt',ns))
    texts = [' '.join(t.text or '' for t in ET.fromstring(z.read(n)).findall('.//a:t', ns)) for n in slides]
    assert 'DADOS ILUSTRATIVOS' in texts[5]
    assert 'ILUSTRATIVO' in texts[6]
print('Validado: 10 slides 16:9, XML íntegro, transições fade e animações com alvos válidos.')
