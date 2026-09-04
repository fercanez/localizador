#!/usr/bin/env python3
import os

os.environ.setdefault("DJANGO_SETTINGS_MODULE", "geonode.local_settings")
import django

django.setup()

from django.contrib.auth.models import AnonymousUser
from django.test import RequestFactory
from geonode.documents.models import Document
from geonode.documents.views import document_metadata_detail
from geonode.middleware_mexicali.forbidden_page import MexicaliForbiddenPageMiddleware

docs = list(Document.objects.all()[:5])
print("docs", [(d.id, (d.title or "")[:50]) for d in docs])
if not docs:
    raise SystemExit("no documents")

d = next((x for x in docs if x.id == 180), docs[0])
rf = RequestFactory()
req = rf.get(f"/documents/{d.id}/metadata_detail")
req.user = AnonymousUser()
mw = MexicaliForbiddenPageMiddleware(lambda r: document_metadata_detail(r, str(d.id)))
resp = mw(req)
body = resp.content.decode("utf-8", errors="ignore")
print("doc_id", d.id)
print("status", resp.status_code)
print("logo", "escudo-mexicali" in body)
print("authz", "autorizado" in body.lower())
print("snippet", body[body.find("<h1>") : body.find("<h1>") + 120] if "<h1>" in body else body[:160])
