import socket, atexit, sys
#  from underscore3 import _
try:
    from .idarest_mixins import IdaRestConfiguration, IdaRestLog
except:
    from idarest_mixins import IdaRestConfiguration, IdaRestLog

#  idarest_master_plugin_t.config['master_debug'] = False
#  idarest_master_plugin_t.config['master_info'] = False
#  idarest_master_plugin_t.config['api_prefix'] = '/ida/api/v1.0'
#  idarest_master_plugin_t.config['master_host'] = "127.0.0.1"
#  idarest_master_plugin_t.config['master_port'] = 28612 # hash('idarest75') & 0xffff

interactive = False

class idarest_master_plugin_t(IdaRestConfiguration, IdaRestLog):
    def init(self):
        self.load_configuration()
        if idarest_master_plugin_t.config['master_info']: print("[idarest_master_plugin_t::init]")
        self.master = None

        if not idarest_master_plugin_t.test_bind_port(idarest_master_plugin_t.config['master_port']):
            if idarest_master_plugin_t.config['master_info']: print("[idarest_master_plugin_t::init] skipping (port is already bound)")
            return 'PLUGIN_SKIP'

        self.master = idarest_master()
        idarest_master_plugin_t.instance = self

        #  def cleanup():
            # TODO: make master able to clean up! ffs
            #  self.log("**master.atexit** cleanup")
            #  if worker and worker.is_alive():
                #  self.log("[idarest_master_plugin_t::start::cleanup] stopping..\n")
                #  worker.stop()
                #  self.log("[idarest_master_plugin_t::start::cleanup] joining..\n")
                #  worker.join()
                #  self.log("[idarest_master_plugin_t::start::cleanup] stopped\n")
#  
            #  if timer and timer.is_alive() and not timer.stopped():
                #  self.log("[idarest_master_plugin_t::start::cleanup] stopping..\n")
                #  timer.stop()
                #  self.log("[idarest_master_plugin_t::start::cleanup] joining..\n")
                #  timer.join()
                #  self.log("[idarest_master_plugin_t::start::cleanup] stopped\n")

        #  print('[idarest_master_plugin_t::start] registered atexit cleanup')

        #  atexit.acquire(cleanup)
        return 'PLUGIN_KEEP'

    def run(*args):
        pass

    def term(self):
        if self.master:
            self.master.stop()
        pass

    @staticmethod
    def test_bind_port(lock_name):
        with socket.socket(socket.AF_INET, socket.SOCK_STREAM) as s:
            try:
                s.setsockopt(socket.SOL_SOCKET, socket.SO_REUSEADDR, 1)
                s.bind((idarest_master_plugin_t.config['master_bind_ip'], lock_name))
            except socket.error as e:
                return False
        return True

def idarest_master():
    from http.server import BaseHTTPRequestHandler, HTTPServer
    from socketserver import ThreadingMixIn
    import threading
    import urllib.request, urllib.error, urllib.parse as urlparse
    import requests
    import json
    import time
    import re

    def asBytes(s):
        if isinstance(s, str):
            return s.encode('utf-8')
        return s

    class HTTPRequestError(BaseException):
        def __init__(self, msg, code):
            self.msg = msg
            self.code = code

    class Handler(BaseHTTPRequestHandler):
        hosts = dict()

        def log_message(self, format, *args):
            return

        def renew(self, args):
            key, secret = args['key'], args['secret']
            if 'secret' not in args:
                raise HTTPRequestError("secret param not specified", 400)
            if 'key' not in args:
                raise HTTPRequestError("key param not specified", 400)
            if key not in self.hosts:
                raise HTTPRequestError("key not found", 404)
            if self.hosts[key]['secret'] != secret:
                raise HTTPRequestError("incorrect secret", 403)
            if interactive or idarest_master_plugin_t.config['master_debug']: print("[idarest_master::Handler::renew] renewing existing lock {}".format(key))
            self.hosts[key]['alive'] = time.time()
            return dict(self.hosts[key])

        def acquire(self, args):
            host, lock_name, pid = args['host'], args['lock_name'], args['pid']
            key = host + ':' + lock_name
            secret = key + ':' + pid
            if key in self.hosts:
                if time.time() - self.hosts[key]['alive'] > idarest_master_plugin_t.config['master_lock_timeout']:
                    if interactive or idarest_master_plugin_t.config['master_debug']: print("[idarest_master::Handler::acquire] replacing existing lock {}".format(key))
                else:
                    if interactive or idarest_master_plugin_t.config['master_debug']: print("[idarest_master::Handler::acquire] already locked {}".format(key))
                    raise HTTPRequestError("already locked", 409)

            self.hosts[key] = value = dict({
                    'host': args['host'],
                    'lock_name': args['lock_name'],
                    'key': key,
                    'secret': secret,
                    'alive': time.time(),
                    'failed': 0,
                    'pid': args['pid'],
            })
            return value

        def release(self, args):
            if 'secret' not in args:
                raise HTTPRequestError("secret param not specified", 400)
            if 'key' not in args:
                raise HTTPRequestError("key param not specified", 400)
            key, secret = args['key'], args['secret']
            if key not in self.hosts:
                raise HTTPRequestError("key not found", 404)
            if self.hosts[key]['secret'] != secret:
                raise HTTPRequestError("incorrect secret", 403)
            self.hosts[key]['alive'] = time.time()
            if interactive or idarest_master_plugin_t.config['master_debug']: print("[idarest_master::Handler::release] removing existing lock {}".format(key))
            value = self.hosts.pop(key)
            return value

        @staticmethod
        def get_json(hosts, args, readonly=False):
            #  r = requests.post(self.url, data=self.args)
            results = dict()
            start = time.time()
            if readonly:
                for k, host in self.hosts.items():
                    if idarest_master_plugin_t.config['master_debug']: print("alive: {}".format(start - host['alive']))
                    if start - host['alive'] < 90:
                        results[key] = 'http://{}:{}{}/'.format(host['host'], host['lock_name'], idarest_master_plugin_t.config['api_prefix'])
                    #  else:
                        #  results[host['idb']] = start - host['alive']
                return results

            for k, host in self.hosts.copy().items():
                start = time.time()
                url = 'http://{}:{}{}/echo'.format(host['host'], host['lock_name'], idarest_master_plugin_t.config['api_prefix'])
                try:
                    connect_timeout = 10
                    read_timeout = 10
                    r = requests.get(url, params=args, timeout=(connect_timeout, read_timeout))
                    if r.status_code == 200:
                        self.hosts[k]['alive'] = start
                        self.hosts[k]['rtime'] = r.elapsed.total_seconds()
                        #  self.hosts[k]['info'] = r.json()
                        results[k] = host
                except Exception as e:
                    results[k] = str(type(e))
                    self.hosts[k]['failed'] += 1
                    if self.hosts[k]['failed'] > 4:
                        self.hosts.pop(k)

            return results


        def show(self, args):
            return self.hosts
            #  return self.get_json(self.hosts, {'ping': time.time()}, readonly=True)

        def fail(self, args):
            if 'secret' not in args:
                raise HTTPRequestError("secret param not specified", 400)
            found = _.find(self.hosts, lambda x, *a: x['secret'] == args['secret'])
            print("[fail] found:{}, type(found):{}".format(found, type(found)))
            keys = [x for x, y in self.hosts.items() if y == found]
            if keys:
                for key in keys:
                    if idarest_master_plugin_t.config['master_debug']: print("[idarest_master::Handler::release] removing existing lock {}".format(key))
                    value = self.hosts.pop(key)
            else:
                value = dict({
                    'host': args['host'],
                    'lock_name': args['lock_name'],
                    'error': 'not registered',
                })
                    

            return value



        def _extract_query_map(self):
            query = urlparse.urlparse(self.path).query
            qd = urlparse.parse_qs(query)
            args = {}
            for k, v in qd.items():
                if len(v) != 1:
                    raise HTTPRequestError(
                        "Query param specified multiple times : " + k,
                        400)
                args[k.lower()] = v[0]
                # if idarest_master_plugin_t.config['master_debug']: print('args["{}"]: "{}"'.format(k.lower(), v[0]))
            return args

        def send_origin_headers(self):
            if self.headers.get('Origin', '') == 'null':
                self.send_header('Access-Control-Allow-Origin', self.headers.get('Origin'))
            self.send_header('Vary', 'Origin')

        def do_GET(self):
            try:
                args = self._extract_query_map()

                path = re.sub(r'.*/', '', urlparse.urlparse(self.path).path)
                if path == 'acquire':
                    message = self.acquire(args)
                elif path == 'release':
                    message = self.release(args)
                elif path == 'renew':
                    message = self.renew(args)
                elif path == 'show':
                    message = self.show(args)
                elif path == 'fail':
                    message = self.fail(args)
                elif path == 'term':
                    globals()['instance'].term()
                elif path == 'restart':
                    # TODO: actually restart
                    globals()['instance'].term()
                else:
                    self.send_error(500, "unknown route: " + path)
                    return

            except HTTPRequestError as e:
                self.send_error(e.code, e.msg)
                return

            self.send_response(200)
            self.send_origin_headers()
            self.end_headers()
            self.wfile.write(asBytes(json.dumps(message)))
            return

    class ThreadedHTTPServer(ThreadingMixIn, HTTPServer):
        allow_reuse_address = True

    # https://stackoverflow.com/questions/323972/is-there-any-way-to-kill-a-thread
    class Timer(threading.Thread):
        def __init__(self,  *args, **kwargs):
            super(Timer, self).__init__(*args, **kwargs)
            self._stop_event = threading.Event()

        def run(self):
            if idarest_master_plugin_t.config['master_info']: print("[idarest_master::Timer::run] started")
            while True:
                if self._stop_event.wait(60.0):
                    break
                result = Handler.get_json(Handler.hosts, {'ping': time.time()})
                if idarest_master_plugin_t.config['master_debug']: print("[idarest_master::Timer::run] {}".format(result))
            if idarest_master_plugin_t.config['master_info']: print("[idarest_master::Timer::run] stopped")

            #  if not self.running:
                #  self.running = True
                #  while self.running:
                    #  time.sleep(60.0 - ((time.time() - self.starttime) % 60.0))
                    #  if idarest_master_plugin_t.config['master_debug']: print(Handler.get_json(Handler.hosts, {'ping': time.time()}))
                #  if idarest_master_plugin_t.config['master_info']: print("[idarest_master::Timer::run] stopped")

        def stop(self):
            if self.is_alive():
                if self.stopped():
                    if idarest_master_plugin_t.config['master_info']: print("[idarest_master::Timer::stop] already stopping...")
                else:
                    if idarest_master_plugin_t.config['master_info']: print("[idarest_master::Timer::stop] stopping...")
                    self._stop_event.set()
            else:
                if idarest_master_plugin_t.config['master_info']: print("[idarest_master::Timer::stop] not running")

        def stopped(self):
            return self._stop_event.is_set()

    class Worker(threading.Thread):
        def __init__(self, host, lock_name):
            threading.Thread.__init__(self)
            self.httpd = ThreadedHTTPServer((host, lock_name), Handler)
            self.host = host
            self.lock_name = lock_name

        def run(self):
            if idarest_master_plugin_t.config['master_info']: print("[idarest_master::Worker::run] master httpd starting...")
            self.httpd.serve_forever()
            if idarest_master_plugin_t.config['master_info']: print("[idarest_master::Worker::run] master httpd started (well stopped now, i guess)")

        def stop(self):
            if idarest_master_plugin_t.config['master_info']: print("[idarest_master::Worker::stop] master httpd shutdown...")
            self.httpd.shutdown()
            if idarest_master_plugin_t.config['master_info']: print("[idarest_master::Worker::stop] master httpd server_close...")
            self.httpd.server_close()
            if idarest_master_plugin_t.config['master_info']: print("[idarest_master::Worker::stop] master httpd stopped")

    class Master:
        def __init__(self):
            # self.worker = Worker('127.0.0.1', 28612)
            self.worker = Worker(idarest_master_plugin_t.config['master_bind_ip'], idarest_master_plugin_t.config['master_port'])
            self.worker.start()
            #  self.test_worker = Timer()
            #  self.test_worker.start()

        def stop(self):
            self.worker.stop()
            #  self.test_worker.stop()

    def main():
        if interactive or idarest_master_plugin_t.config['master_info']: print("[idarest_master::main] starting master")
        master = Master()
        #  main.master = master
        return master

    return main()

if sys.stdin and sys.stdin.isatty():
    # running interactively
    print("running interactively")
    interactive = True

if __name__ == "__main__":
    master = idarest_master_plugin_t()
    master.init()
