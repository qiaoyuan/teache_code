#!/usr/bin/env python3
"""Real-cookie/CSRF smoke test. Run only against an explicitly isolated local test server."""
import argparse, http.cookiejar, json, urllib.request, urllib.error, urllib.parse, zipfile, io
parser=argparse.ArgumentParser();parser.add_argument('--url',required=True);parser.add_argument('--isolated-test',action='store_true');args=parser.parse_args()
assert args.isolated_test and args.url.startswith('http://127.0.0.1:'), 'Requires an isolated localhost test server'
class Client:
 def __init__(self):
  self.cookies=http.cookiejar.CookieJar();self.opener=urllib.request.build_opener(urllib.request.HTTPCookieProcessor(self.cookies));self.csrf={}
 def call(self,portal,path,method='GET',data=None,status=200,token=None,raw=False):
  headers={'Accept':'application/json','Content-Type':'application/json'}
  if method!='GET': headers['X-CSRF-TOKEN']=token if token is not None else self.csrf.get(portal,'')
  request=urllib.request.Request(args.url+'/api/v1/'+portal+'/'+path,data=None if data is None else json.dumps(data).encode(),method=method,headers=headers)
  try:r=self.opener.open(request)
  except urllib.error.HTTPError as e:r=e
  body=r.read();assert r.status==status,(portal,path,r.status,body[:400])
  if raw:return body
  result=json.loads(body or '{}')
  if result.get('csrf_token'):self.csrf[portal]=result['csrf_token']
  return result
c=Client();other=Client();c.call('app','auth/csrf');other.call('app','auth/csrf')
import re
register_html=c.opener.open(args.url+'/app/register').read().decode()
agreement_version=re.search(r'<meta name="agreement-version" content="([^"]+)"',register_html).group(1)
agreement={'agreement_accepted':True,'agreement_version':agreement_version}
c.call('app','students',status=401);c.call('app','auth/register','POST',{'phone':'13800000001','password':'ParentPass123','password_confirmation':'ParentPass123',**agreement},201)
student=c.call('app','students','POST',{'name':'林小禾（测试）','gender':'female','school':'实验小学','entry_year':2021,'entry_grade':'P1','current_grade':'P6','contact_phone':'13900000001'},201)['data']
sid=student['id']
for date,title,math,maxscore,grade in [('2026-09-01','开学摸底考试',88,100,'A'),('2026-10-01','秋季月考',106,120,'优秀')]:
 e=c.call('app',f'students/{sid}/exams','POST',{'title':title,'exam_date':date,'grade_at_exam':'P6','school_at_exam':'实验小学','note':'隔离测试库中的演示记录','subjects':[{'name':'语文','mode':'score','score':92,'max_score':100},{'name':'数学','mode':'score','score':math,'max_score':maxscore},{'name':'英语','mode':'grade','grade':grade}]},201)['data']
c.call('app',f'students/{sid}/intentions','POST',{'school':'实验中学','level':'first','note':'关注课程和校园环境'},201)
c.call('app',f'students/{sid}/intentions','POST',{'school':'育才中学','level':'backup','note':'通勤方便'},201)
other.call('app','auth/register','POST',{'phone':'13800000002','password':'OtherPass123','password_confirmation':'OtherPass123',**agreement},201)
other.call('app',f'students/{sid}',status=403);other.call('app',f'exams/{e["id"]}',status=403)
c.call('admin','auth/csrf');c.call('admin','auth/login','POST',{'username':'preview_admin','password':'PreviewAdmin123'})
c.call('app','auth/me');c.call('admin','auth/me')
names={cookie.name for cookie in c.cookies};assert 'growth_parent_session' in names and 'growth_admin_session' in names
c.call('app','students','POST',{'name':'被拒绝'},419,token=c.csrf['admin'])
c.call('app','auth/logout','POST');c.call('admin','overview');c.call('app','auth/me',status=401)
c.call('app','auth/login','POST',{'phone':'13800000001','password':'ParentPass123'});c.call('admin','overview')
parent_id=c.call('app','auth/me')['user']['id']
c.call('admin',f'parents/{parent_id}/reset-password','POST',{'password':'TempPass456','password_confirmation':'TempPass456'})
c.call('app','students',status=401);c.call('app','auth/csrf');c.call('app','auth/login','POST',{'phone':'13800000001','password':'ParentPass123'},401)
c.call('app','auth/csrf');c.call('app','auth/login','POST',{'phone':'13800000001','password':'TempPass456'})
c.call('app','students',status=403);c.call('app','auth/change-password','POST',{'current_password':'TempPass456','password':'PreviewParent123','password_confirmation':'PreviewParent123'})
c.call('app','students');c.call('admin','overview')
q=urllib.parse.urlencode({'school':'实验','grade':'P6','phone':'00001'})
assert c.call('admin','scores?'+q)['total']==6
blob=c.call('admin','exports/all',raw=True);z=zipfile.ZipFile(io.BytesIO(blob));assert len([n for n in z.namelist() if n.startswith('xl/worksheets/sheet') and n.endswith('.xml')])==3
assert c.call('admin','audit-logs')['total']==2
c.call('admin','auth/logout','POST');c.call('app','auth/me');c.call('admin','auth/me',status=401)
print('PASS: real cookies, portal isolation, CSRF rejection, CRUD, forbidden access, reset/re-login/change, filters, XLSX, audit')
