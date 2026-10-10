from unittest.mock import Mock
from app.knowledge import KnowledgeBase
from app.tutor import Tutor


def test_nios_discovery_does_not_trust_external_or_internal_urls(monkeypatch):
    response=Mock();response.raise_for_status.return_value=None
    response.text='''<a href="http://127.0.0.1/private.pdf">B Level Maths</a>
    <a href="https://evil.example/evs.pdf">B Level EVS</a>
    <a href="https://cdn.nios.ac.in/official.pdf">B Level Basic Computer Skills</a>'''
    monkeypatch.setattr('app.knowledge.requests.get',Mock(return_value=response))
    found=KnowledgeBase()._discover_nios()
    assert (5,'math') not in found
    assert (5,'evs') not in found
    assert found[(5,'computer')]['official'] is True


def test_unknown_curriculum_subject_never_searches_local_documents():
    kb=KnowledgeBase();kb.local_docs=[{'text':'private unrelated chapter','source':'unrelated'}]
    assert kb.search('private',curriculum='unknown',subject='unknown')==[]


def test_nios_fallback_is_labelled_supplemental(monkeypatch):
    kb=KnowledgeBase();monkeypatch.setattr(kb,'_discover_nios',lambda:{})
    item=next(x for x in kb.catalog()['nios']['5'] if x['id']=='math')
    assert item['supplemental'] is True
    assert item['official'] is False
    assert item['source_url'].startswith('https://books.ebalbharati.in/')


def test_incomplete_model_output_cannot_create_assessment():
    parsed=Tutor()._parse('{"reply":"Please try again", "assessment":"correct"')
    assert parsed['text']=='Please try again'
    assert parsed['assessment']=='not_answer'
    assert parsed['_parse_ok'] is False


def test_model_cannot_add_html_to_choice_widget():
    parsed=Tutor()._parse('{"reply":"Choose one", "interaction":{"type":"true_false","options":["<script>"]}}')
    assert parsed['interaction']['options']==['True','False']
