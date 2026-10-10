from datetime import datetime, timedelta, timezone
from unittest.mock import Mock
import pytest
import requests
from fastapi import HTTPException
from fastapi.testclient import TestClient
from app import main, lesson_routes as lessons

@pytest.mark.parametrize('module', [main, lessons])
@pytest.mark.parametrize('header', [None, '', 'Basic abc', 'Bearer ', 'Bearer    '])
def test_missing_bearer_is_rejected(module, header):
    with pytest.raises(HTTPException) as exc:
        module._token(header)
    assert exc.value.status_code == 401

@pytest.mark.parametrize('user_id', ['other-parent', 'student', None])
def test_parent_checks_exact_owner(monkeypatch, user_id):
    monkeypatch.setattr(main, '_require_user', lambda t: {'id':user_id})
    with pytest.raises(HTTPException) as exc:
        main._require_parent('token', {'parent_id':'owner'})
    assert exc.value.status_code == 403

def test_parent_can_manage_own_child(monkeypatch):
    monkeypatch.setattr(main, '_require_user', lambda t: {'id':'owner'})
    main._require_parent('token', {'parent_id':'owner'})

@pytest.mark.parametrize('endpoint', ['/api/chat','/api/tts','/api/end-session','/api/lessons/start','/api/lessons/override'])
def test_protected_endpoints_reject_unauthenticated_request(endpoint):
    payload={'message':'hello','text':'hello','student_id':'child','session_id':'session','curriculum':'maharashtra','subject':'math','lesson_id':'first'}
    response=TestClient(main.app).post(endpoint,json=payload)
    assert response.status_code == 401
    assert response.headers['cache-control']=='no-store'

def test_cross_subject_session_cannot_write_or_call_provider(monkeypatch):
    monkeypatch.setattr(main,'_student',lambda *a:{'id':'child','full_name':'Test','grade':3})
    response=Mock(ok=True);response.json.return_value=[{'id':'session','subject':'nios:math'}]
    monkeypatch.setattr(main.requests,'get',Mock(return_value=response))
    insert=Mock();monkeypatch.setattr(main,'_insert',insert)
    provider=Mock();monkeypatch.setattr(main.tutor,'reply',provider)
    r=TestClient(main.app).post('/api/chat',headers={'Authorization':'Bearer token'},json={'student_id':'child','session_id':'session','message':'4','subject':'math','curriculum':'maharashtra'})
    assert r.status_code==403
    insert.assert_not_called();provider.assert_not_called()

def test_supabase_timeout_returns_generic_gateway_error(monkeypatch):
    monkeypatch.setattr(main,'SUPABASE_URL','https://example.invalid')
    monkeypatch.setattr(main,'SUPABASE_ANON_KEY','public')
    monkeypatch.setattr(main.requests,'get',Mock(side_effect=requests.Timeout('secret-host')))
    r=TestClient(main.app).get('/api/dashboard?student_id=child',headers={'Authorization':'Bearer token'})
    assert r.status_code==502
    assert 'secret-host' not in r.text

def test_repeated_session_close_filters_already_ended_rows(monkeypatch):
    monkeypatch.setattr(main,'_student',Mock())
    response=Mock(ok=True);response.json.return_value=[{'id':'session'}]
    monkeypatch.setattr(main.requests,'get',Mock(return_value=response))
    patch=Mock();monkeypatch.setattr(main,'_patch',patch)
    main.end_session(main.EndSessionIn(student_id='child',session_id='session'),'Bearer token')
    assert patch.call_args.args[2]=={'id':'eq.session','student_id':'eq.child','ended_at':'is.null'}

def test_week_contains_exactly_seven_calendar_days_and_handles_naive_timestamps():
    now=datetime.now(timezone.utc)
    sessions=[{'started_at':(now-timedelta(days=7)).isoformat(),'subject':'math'}, {'started_at':(now-timedelta(minutes=10)).replace(tzinfo=None).isoformat(),'ended_at':now.replace(tzinfo=None).isoformat(),'subject':'math'}]
    metrics=main._week_metrics(sessions,[])
    assert metrics['sessions']==1
    assert metrics['study_minutes']==10
