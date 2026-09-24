#!/usr/bin/env python3
"""Resilient Easy MCP AI client: body via temp file, exponential backoff, JSON result parsing."""
import json, subprocess, time, tempfile, os, sys, random
UA="Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/124.0 Safari/537.36"
KEY=os.environ.get("WPMCP_KEY","")
URL=os.environ.get("WPMCP_URL","https://trix.ebaem.com.br/wp-json/easy-mcp-ai/v1/mcp")
LOG=open(os.path.join(os.path.dirname(os.path.abspath(__file__)),"wpmcp.log"),"a")
class MCPError(Exception): pass
def call(name, args=None, tries=10, timeout=240):
    body=json.dumps({"jsonrpc":"2.0","id":random.randint(1,10**6),"method":"tools/call","params":{"name":name,"arguments":args or {}}},ensure_ascii=False)
    fd,path=tempfile.mkstemp(suffix=".json"); os.write(fd,body.encode()); os.close(fd)
    delay=1.5; last=""
    try:
        for i in range(tries):
            t0=time.time()
            p=subprocess.run(["curl","-sS","-m",str(timeout),"--http1.1","-A",UA,"-H","Authorization: Bearer "+KEY,"-H","Content-Type: application/json; charset=utf-8","-H","Accept: application/json, text/event-stream","-X","POST",URL,"--data-binary","@"+path],capture_output=True,text=True)
            dt=time.time()-t0
            if p.returncode==0 and p.stdout.strip():
                out=p.stdout; j=out.find("{")
                try: d=json.loads(out[j:])
                except Exception: last="bad json: "+out[:200]; LOG.write(f"{name} try{i} badjson {dt:.1f}s\n"); time.sleep(delay); delay=min(delay*2,10); continue
                if "error" in d: raise MCPError(json.dumps(d["error"],ensure_ascii=False)[:800])
                r=d.get("result",{})
                if isinstance(r,dict) and "content" in r:
                    text="\n".join(c.get("text","") for c in r["content"] if c.get("type")=="text")
                    if r.get("isError"): raise MCPError(text[:800])
                    try: return json.loads(text)
                    except Exception: return {"_text":text}
                return r
            last=(p.stderr or p.stdout)[:200]; LOG.write(f"{name} try{i} fail {dt:.1f}s {last.strip()}\n"); LOG.flush()
            time.sleep(delay+random.random()); delay=min(delay*2,10)
        raise MCPError(f"{name}: gave up after {tries} tries: {last}")
    finally:
        os.unlink(path)
if __name__=="__main__":
    name=sys.argv[1]; args=json.loads(sys.argv[2]) if len(sys.argv)>2 else {}
    print(json.dumps(call(name,args),ensure_ascii=False,indent=1)[:int(sys.argv[3]) if len(sys.argv)>3 else 4000])
