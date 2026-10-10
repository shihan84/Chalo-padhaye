import asyncio
from unittest.mock import AsyncMock, Mock
from fastapi.testclient import TestClient
from app import main
from app.security import BodySizeLimit


def test_large_body_is_rejected_before_auth_and_parsing(monkeypatch):
    auth=Mock();monkeypatch.setattr(main,'_student',auth)
    response=TestClient(main.app).post('/api/chat',content=b'x'*65537,headers={'Content-Type':'application/json'})
    assert response.status_code==413
    auth.assert_not_called()


def test_chunked_body_limit_is_not_dependent_on_content_length():
    inner=AsyncMock();chunks=iter([{'type':'http.request','body':b'x'*32768,'more_body':True},{'type':'http.request','body':b'x'*32769,'more_body':False}]);sent=[]
    async def receive():return next(chunks)
    async def send(message):sent.append(message)
    asyncio.run(BodySizeLimit(inner)({'type':'http','method':'POST'},receive,send))
    assert sent[0]['status']==413
    inner.assert_not_called()


def test_small_streamed_request_is_preserved():
    chunks=iter([{'type':'http.request','body':b'{"text":','more_body':True},{'type':'http.request','body':b'"hello"}','more_body':False}]);seen=[]
    async def receive():return next(chunks)
    async def send(message):pass
    async def inner(scope, receive, send):seen.append((await receive())['body'])
    asyncio.run(BodySizeLimit(inner)({'type':'http','method':'POST'},receive,send))
    assert seen==[b'{"text":"hello"}']
