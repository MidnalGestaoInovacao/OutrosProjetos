#!/usr/bin/env python3
"""Minimal MCP Streamable-HTTP client for Easy MCP AI (WordPress) using curl."""
import json, sys, time, os, subprocess, tempfile

KEY = os.environ.get("WPMCP_KEY") or (open(os.path.join(os.path.dirname(os.path.abspath(__file__)), ".wpmcp_key")).read().strip() if os.path.exists(os.path.join(os.path.dirname(os.path.abspath(__file__)), ".wpmcp_key")) else "")
if not KEY:
    raise SystemExit("Defina a chave do Easy MCP AI na variável de ambiente WPMCP_KEY (ou no arquivo .wpmcp_key ao lado deste script).")
URL = f"https://evellynbrandao.com.br/wp-json/easy-mcp-ai/v1/mcp/{KEY}"
HERE = os.path.dirname(os.path.abspath(__file__))
SESSION_FILE = os.path.join(HERE, ".mcp_session")
_id = [int(time.time()) % 100000]

def _post(payload, session=None, retries=15):
    last = None
    with tempfile.NamedTemporaryFile("w", suffix=".json", delete=False, dir=HERE) as f:
        json.dump(payload, f)
        body_path = f.name
    try:
        for attempt in range(retries):
            hdr_path = body_path + ".hdr"
            cmd = ["curl", "-sS", "-m", "90", "--http1.1", "-D", hdr_path, "-X", "POST", URL,
                   "-H", "Content-Type: application/json",
                   "-H", "Accept: application/json, text/event-stream",
                   "--data-binary", "@" + body_path]
            if session:
                cmd += ["-H", f"Mcp-Session-Id: {session}"]
            p = subprocess.run(cmd, capture_output=True, text=True)
            if p.returncode == 0:
                sid = None
                status = None
                try:
                    for line in open(hdr_path).read().splitlines():
                        if line.lower().startswith("mcp-session-id:"):
                            sid = line.split(":", 1)[1].strip()
                        if line.startswith("HTTP/") and "Connection Established" not in line:
                            status = int(line.split()[1])
                except Exception:
                    pass
                body = p.stdout
                if status and status >= 400:
                    return {"error": {"code": status, "message": body[:2000]}}, sid
                if not body.strip():
                    return None, sid
                if body.lstrip().startswith("event:") or body.lstrip().startswith("data:"):
                    out = None
                    for line in body.splitlines():
                        if line.startswith("data:"):
                            try:
                                out = json.loads(line[5:].strip())
                            except Exception:
                                pass
                    return out, sid
                try:
                    return json.loads(body), sid
                except Exception:
                    last = "non-json body: " + body[:500]
            else:
                last = p.stderr.strip()[:500]
            time.sleep(min(1.0 * (attempt + 1), 6))
        raise RuntimeError(f"MCP request failed after {retries} attempts: {last}")
    finally:
        for pth in (body_path, body_path + ".hdr"):
            try: os.remove(pth)
            except Exception: pass

def init():
    _id[0] += 1
    res, sid = _post({"jsonrpc": "2.0", "id": _id[0], "method": "initialize",
                      "params": {"protocolVersion": "2025-03-26", "capabilities": {},
                                 "clientInfo": {"name": "claude-code", "version": "1.0"}}})
    if not sid:
        raise RuntimeError(f"no session id: {res}")
    with open(SESSION_FILE, "w") as f:
        f.write(sid)
    _post({"jsonrpc": "2.0", "method": "notifications/initialized"}, session=sid)
    return sid

def session():
    if os.path.exists(SESSION_FILE):
        return open(SESSION_FILE).read().strip()
    return init()

def rpc(method, params=None, _retry=True):
    sid = session()
    _id[0] += 1
    res, _ = _post({"jsonrpc": "2.0", "id": _id[0], "method": method, "params": params or {}}, session=sid)
    if res is None:
        return None
    if "error" in res and _retry:
        msg = json.dumps(res["error"]).lower()
        if "session" in msg or res["error"].get("code") in (400, 404, -32000, -32001):
            init()
            return rpc(method, params, _retry=False)
    return res

def call(tool, args=None):
    res = rpc("tools/call", {"name": tool, "arguments": args or {}})
    if res is None:
        return None
    if "error" in res:
        return {"error": res["error"]}
    result = res.get("result", {})
    if isinstance(result, dict) and "content" in result:
        texts = []
        for c in result["content"]:
            if c.get("type") == "text":
                t = c.get("text", "")
                try:
                    texts.append(json.loads(t))
                except Exception:
                    texts.append(t)
        if result.get("isError"):
            return {"error": texts}
        return texts[0] if len(texts) == 1 else texts
    return result

if __name__ == "__main__":
    if len(sys.argv) < 2:
        print("usage: mcp.py <method> [json-params]  |  mcp.py call <tool> [json-args]")
        sys.exit(1)
    if sys.argv[1] == "call":
        args = json.loads(sys.argv[3]) if len(sys.argv) > 3 else {}
        print(json.dumps(call(sys.argv[2], args), ensure_ascii=False, indent=1))
    else:
        params = json.loads(sys.argv[2]) if len(sys.argv) > 2 else {}
        print(json.dumps(rpc(sys.argv[1], params), ensure_ascii=False, indent=1))
