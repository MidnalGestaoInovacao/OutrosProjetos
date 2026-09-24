#!/usr/bin/env python3
"""Cliente Easy MCP AI (WordPress) resiliente.
O caminho de rede até trix.ebaem.com.br falha com frequência no handshake TLS (o túnel do proxy encerra após ~11 s),
mas uma conexão estabelecida atende várias requisições em sequência. Por isso o cliente mantém UMA conexão TLS
persistente (keep-alive) e só refaz o handshake quando o servidor a encerra."""
import json, os, sys, time, random, ssl, http.client, socket, urllib.parse
UA = "Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/124.0 Safari/537.36"
KEY = os.environ.get("WPMCP_KEY", "")
URL = os.environ.get("WPMCP_URL", "https://trix.ebaem.com.br/wp-json/easy-mcp-ai/v1/mcp")
CA = os.environ.get("WPMCP_CA", "/root/.ccr/ca-bundle.crt")
PROXY = os.environ.get("HTTPS_PROXY") or os.environ.get("https_proxy")
HANDSHAKE_TIMEOUT = float(os.environ.get("WPMCP_HANDSHAKE_TIMEOUT", "14"))
LOG = open(os.path.join(os.path.dirname(os.path.abspath(__file__)), "wpmcp.log"), "a")
def _log(msg): LOG.write(time.strftime("%H:%M:%S ") + msg + "\n"); LOG.flush()
class MCPError(Exception): pass
class Client:
    def __init__(self):
        u = urllib.parse.urlsplit(URL); self.host, self.path = u.hostname, u.path
        self.ctx = ssl.create_default_context(cafile=CA if os.path.exists(CA) else None)
        self.conn = None; self.stats = {"handshake_fail": 0, "handshake_ok": 0, "calls": 0, "reconnects": 0}
    def _connect(self):
        if PROXY:
            pu = urllib.parse.urlsplit(PROXY)
            c = http.client.HTTPSConnection(pu.hostname, pu.port, context=self.ctx, timeout=HANDSHAKE_TIMEOUT)
            c.set_tunnel(self.host, 443, headers={"User-Agent": UA})
        else:
            c = http.client.HTTPSConnection(self.host, 443, context=self.ctx, timeout=HANDSHAKE_TIMEOUT)
        c.connect(); return c
    def ensure(self, max_attempts=60):
        if self.conn: return self.conn
        delay = 1.0
        for i in range(max_attempts):
            try:
                self.conn = self._connect(); self.stats["handshake_ok"] += 1; _log("handshake ok (attempt %d)" % (i + 1)); return self.conn
            except Exception as e:
                self.stats["handshake_fail"] += 1; _log("handshake fail %d: %s" % (i + 1, str(e)[:80]))
                time.sleep(delay + random.random()); delay = min(delay * 1.4, 12)
        raise MCPError("não foi possível estabelecer conexão TLS após %d tentativas" % max_attempts)
    def close(self):
        try:
            if self.conn: self.conn.close()
        except Exception: pass
        self.conn = None
    def post(self, body, read_timeout=300):
        """Envia uma requisição na conexão persistente; retorna (status, texto). Reconecta se o servidor fechou a conexão ociosa."""
        for attempt in range(3):
            c = self.ensure()
            try:
                c.sock.settimeout(read_timeout)
                c.request("POST", self.path, body=body, headers={"User-Agent": UA, "Authorization": "Bearer " + KEY, "Content-Type": "application/json; charset=utf-8", "Accept": "application/json, text/event-stream", "Connection": "keep-alive"})
                r = c.getresponse(); data = r.read().decode("utf-8", "replace")
                if r.getheader("Connection", "").lower() == "close": self.close()
                return r.status, data
            except (http.client.RemoteDisconnected, http.client.BadStatusLine, ConnectionResetError, BrokenPipeError, socket.timeout, ssl.SSLError, OSError) as e:
                _log("request failed on live connection (%s); reconnecting" % type(e).__name__); self.close(); self.stats["reconnects"] += 1
                if attempt == 2: raise MCPError("falha de transporte: %s" % e)
                time.sleep(1.5)
_client = None
def client():
    global _client
    if _client is None: _client = Client()
    return _client
def call(name, args=None, tries=3, timeout=300, pause=None):
    body = json.dumps({"jsonrpc": "2.0", "id": random.randint(1, 10 ** 6), "method": "tools/call", "params": {"name": name, "arguments": args or {}}}, ensure_ascii=False).encode("utf-8")
    last = ""
    for i in range(tries):
        t0 = time.time()
        try:
            status, out = client().post(body, read_timeout=timeout)
        except MCPError as e:
            last = str(e); _log("%s try%d transport error %.1fs %s" % (name, i, time.time() - t0, last[:80])); time.sleep(pause or 3); continue
        client().stats["calls"] += 1
        j = out.find("{")
        try: d = json.loads(out[j:])
        except Exception:
            last = "resposta inválida (HTTP %s): %s" % (status, out[:200]); _log("%s try%d badjson %.1fs http=%s" % (name, i, time.time() - t0, status)); time.sleep(pause or 3); continue
        if "error" in d: raise MCPError(json.dumps(d["error"], ensure_ascii=False)[:800])
        r = d.get("result", {})
        if isinstance(r, dict) and "content" in r:
            text = "\n".join(c.get("text", "") for c in r["content"] if c.get("type") == "text")
            if r.get("isError"): raise MCPError(text[:800])
            try: return json.loads(text)
            except Exception: return {"_text": text}
        return r
    raise MCPError("%s: desistiu após %d tentativas: %s" % (name, tries, last))
def healthy(max_wait=0):
    """Compatibilidade: com a conexão persistente, a 'saúde' é simplesmente conseguir conectar."""
    try: client().ensure(); return True
    except MCPError: return False
if __name__ == "__main__":
    name = sys.argv[1]; args = json.loads(sys.argv[2]) if len(sys.argv) > 2 else {}
    print(json.dumps(call(name, args), ensure_ascii=False, indent=1)[:int(sys.argv[3]) if len(sys.argv) > 3 else 4000])
