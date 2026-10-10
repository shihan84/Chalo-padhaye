from datetime import datetime, timedelta, timezone
from unittest.mock import Mock

import pytest
from fastapi import HTTPException
from fastapi.testclient import TestClient

from app import main, lesson_routes as lessons
from app.combined_knowledge import CBSEKnowledgeBase
from app.tutor import Tutor

STUDENT = {'id': 'child', 'full_name': 'Test Child', 'grade': 3, 'parent_id': 'parent'}
LESSON = lessons._subject_lessons('maharashtra', 3, 'math')[0]
BASE = dict(student_id='child', curriculum='maharashtra', subject='math', lesson_id=LESSON['id'])


def test_student_cannot_use_parent_override(monkeypatch):
    monkeypatch.setattr(lessons, '_student', lambda *a: STUDENT)
    monkeypatch.setattr(main, '_require_user', lambda t: {'id': 'linked-student'})
    write = Mock(return_value={'id': 'progress'})
    monkeypatch.setattr(lessons, '_get_one', lambda *a: None)
    monkeypatch.setattr(lessons, '_post', write)
    monkeypatch.setattr(lessons, '_ensure_steps', lambda *a: [])
    with pytest.raises(HTTPException) as exc:
        lessons.lesson_override(lessons.OverrideIn(**BASE, action='master'), 'Bearer valid')
    assert exc.value.status_code == 403
    write.assert_not_called()


@pytest.mark.parametrize('count', [0, 1, 4, 6])
def test_test_requires_full_question_count(monkeypatch, count):
    monkeypatch.setattr(lessons, '_student', lambda *a: STUDENT)
    monkeypatch.setattr(lessons, '_ensure_progress', lambda *a: {'id':'p','status':'test_ready','test_attempts':0})
    writes = Mock()
    monkeypatch.setattr(lessons, '_post', writes)
    monkeypatch.setattr(lessons, '_get_one', lambda *a: None)
    monkeypatch.setattr(lessons, '_patch', Mock(return_value={}))
    with pytest.raises(HTTPException) as exc:
        lessons.lesson_test(lessons.TestAttemptIn(**BASE, question_count=count, correct_count=1), 'Bearer valid')
    assert exc.value.status_code == 400
    writes.assert_not_called()


def test_completed_step_replay_does_not_regress(monkeypatch):
    monkeypatch.setattr(lessons, '_student', lambda *a: STUDENT)
    progress = {'id':'p','status':'test_ready','current_step':'test','percent_complete':75}
    monkeypatch.setattr(lessons, '_ensure_progress', lambda *a: progress)
    monkeypatch.setattr(lessons, '_ensure_steps', lambda *a: [])
    step = {'id':'s','step_id':'learn','status':'completed','attempts':2,'correct_count':1}
    monkeypatch.setattr(lessons, '_get_one', lambda *a: step)
    write = Mock(return_value={})
    monkeypatch.setattr(lessons, '_patch', write)
    result = lessons.lesson_step(lessons.StepEventIn(**BASE, step_id='learn', assessment='correct'), 'Bearer valid')
    assert result['progress'] == progress
    write.assert_not_called()


def test_failed_review_preserves_mastery(monkeypatch):
    monkeypatch.setattr(lessons, '_student', lambda *a: STUDENT)
    progress = {'id':'p','status':'mastered','current_step':'test','test_score':100,'test_attempts':1,'mastered_at':'2026-01-01'}
    monkeypatch.setattr(lessons, '_ensure_progress', lambda *a: progress)
    monkeypatch.setattr(lessons, '_post', lambda *a: {})
    monkeypatch.setattr(lessons, '_get_one', lambda *a: None)
    writes = []
    def patch(token, table, params, payload):
        writes.append((table, payload))
        return {**progress, **payload}
    monkeypatch.setattr(lessons, '_patch', patch)
    result = lessons.lesson_test(lessons.TestAttemptIn(**BASE, correct_count=0), 'Bearer valid')
    assert result['passed'] is False
    assert result['progress']['status'] == 'mastered'
    assert result['progress']['mastered_at'] == '2026-01-01'
    assert result['progress']['percent_complete'] == 100


def test_exact_page_failure_does_not_search_other_pages(monkeypatch):
    kb = CBSEKnowledgeBase()
    monkeypatch.setattr(kb, 'page_context', lambda *a: [])
    build = Mock(side_effect=AssertionError('Must not substitute another page'))
    monkeypatch.setattr(kb, '_build_index', build)
    assert kb.search('[Textbook page: 999; Chapter number: 1]', grade=10, subject='math') == []
    build.assert_not_called()


def test_latest_page_marker_wins(monkeypatch):
    kb = CBSEKnowledgeBase()
    page = Mock(return_value=[{'text':'selected page'}])
    monkeypatch.setattr(kb, 'page_context', page)
    kb.search('[Textbook page: 1; Chapter number: 1]\n[Textbook page: 2; Chapter number: 2]')
    page.assert_called_once_with(10, 'math', 2, 2)


def test_daily_plan_respects_age_limit(monkeypatch):
    monkeypatch.setattr(main.tutor.kb, 'catalog', lambda: {'nios':{'3':[{'id':s} for s in ['math','evs','computer','english','life']]}})
    items = main._daily_plan_for(STUDENT, 'nios', [])
    assert len([x for x in items if x['kind']=='lesson']) == 2
    assert len([x for x in items if x['kind']=='offline_suggestion']) == 2


def test_week_metrics_excludes_future_records():
    tomorrow = (datetime.now(timezone.utc)+timedelta(days=1)).date().isoformat()
    metrics = main._week_metrics([], [{'status':'completed','occurred_on':tomorrow,'minutes':60}])
    assert metrics['total_minutes'] == 0
    assert metrics['active_days'] == 0


def test_provider_errors_are_not_exposed(monkeypatch):
    tutor = Tutor()
    monkeypatch.setattr(tutor.kb, 'search', lambda *a, **k: [])
    monkeypatch.setattr(tutor, '_ollama_reply', Mock(side_effect=RuntimeError('SECRET-internal-host')))
    result = tutor.reply('Hello')
    assert 'SECRET' not in str(result)


def test_api_rejects_oversized_chat_before_auth(monkeypatch):
    student = Mock()
    monkeypatch.setattr(main, '_student', student)
    response = TestClient(main.app).post('/api/chat', json={'student_id':'child','message':'x'*12001})
    assert response.status_code == 422
    student.assert_not_called()
