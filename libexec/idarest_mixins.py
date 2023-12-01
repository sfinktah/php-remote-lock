import os
import sys
import json
import pydoc
from queue import Full
#  from superglobals import *

try:
    import idaapi
    import idc
except:
    class idaapi:
        def get_user_idadir(): return '.'
    class idc:
        def get_idb_path(): return '.'

def GetMyLocalIp():
    import socket
    s = socket.socket(socket.AF_INET, socket.SOCK_DGRAM)
    try:
        s.connect(("nt4.com", 80))
    except socket.error as e:
        raise
    r = s.getsockname()[0]
    s.close()
    return r

def GetMasterLocalIp():
    myip = GetMyLocalIp()
    if myip == '192.168.1.118' or myip == '192.168.1.123':
        return '192.168.1.118'
    return '127.0.0.1'


class Namespace(object):
    pass

class IdaRestLog:
    PROJECT_LOG_FILE = os.path.join( os.path.dirname( idc.get_idb_path() ), "idarest.log" )

    @staticmethod
    def log(msg):
        with open(IdaRestLog.PROJECT_LOG_FILE, 'a') as f:
            f.write(msg.rstrip() + "\n")



class IdaRestConfiguration:

    CFG_FILE = os.path.join(idaapi.get_user_idadir(), "idarest.cfg")
    PROJECT_CFG_FILE = os.path.join( os.path.dirname( idc.get_idb_path() ), "idarest.cfg" )
    config = {
       'api_bind_ip':  GetMyLocalIp(),
       'api_host':     '127.0.0.1',
       'api_port':     2000,

       'master_host':         GetMasterLocalIp(),
       'master_bind_ip':      '0.0.0.0',
       'master_port':         28612,
       'master_lock_timeout': 300,

       'api_prefix':   '/ida/api/v1.0',

       'api_verbose':  True, #,
       'api_debug':    True, #,
       'api_info':     True,
       'master_debug': True, #,
       'master_info':  True, #,
       'client_debug': True, #,
       'client_info':  True,

       'client_connect_timeout': 2,
       'client_read_timeout': 2,
       'client_update_hosts_timeout': 2,

       'api_queue_result_qget_timeout': 10,
    }

    @staticmethod
    def _each(obj, func):
        """
        iterates through _each item of an object
        :param: obj object to iterate
        :param: func iterator function

        underscore.js:
        Iterates over a list of elements, yielding each in turn to an iteratee
        function.  Each invocation of iteratee is called with three arguments:
        (element, index, list).  If list is a JavaScript object, iteratee's
        arguments will be (value, key, list). Returns the list for chaining.
        """
        if isinstance(obj, dict):
            for key, value in obj.items():
                func(value, key, obj)
        else:
            for index, value in enumerate(obj):
                r = func(value, index, obj)
        return obj

    @staticmethod
    def _defaults(obj, *args):
        """ Fill in a given object with default properties.
        """
        ns = Namespace()
        ns.obj = obj

        def by(source, *a):
            for i, prop in enumerate(source):
                if prop not in ns.obj:
                    ns.obj[prop] = source[prop]

        IdaRestConfiguration._each(args, by)

        return ns.obj

        
    @classmethod
    def load_configuration(self):
       # default
  
        # load configuration from file
        saved_config = {}
        try:
            f = open(self.CFG_FILE, "r")
            self.config.update(json.load(f))
            saved_config = self.config.copy()
            f.close()
            print("[IdaRestConfiguration::load_configuration] loaded global config file")
        except IOError:
            print("[IdaRestConfiguration::load_configuration] failed to load global config file, using defaults")
        except Exception as e:
            print("[IdaRestConfiguration::load_configuration] failed to load global config file: {0}".format(str(e)))
   
        # use default values if not defined in config file
        #  self._defaults(self.config, {
           #  'api_host':     '127.0.0.1',
           #  'api_port':     2000,
#  
           #  'master_host':  '127.0.0.1',
           #  'master_port':  28612,
#  
           #  'api_prefix':   '/ida/api/v1.0',
#  
           #  'api_verbose':    False,
           #  'api_debug':    False,
           #  'api_info':     True,
           #  'master_debug': False,
           #  'master_info':  False,
           #  'client_debug': True,
           #  'client_info':  True,
#  
           #  'api_queue_result_qget_timeout': 10,
        #  })

        if self.config != saved_config:
            try:
                json.dump(self.config, open(self.CFG_FILE, "w"), indent=4)
                print("[IdaRestConfiguration::load_configuration] global configuration saved to {0}".format(self.CFG_FILE))
            except Exception as e:
                print("[IdaRestConfiguration::load_configuration] failed to save global config file, with exception: {0}".format(str(e)))

        if os.path.exists(self.PROJECT_CFG_FILE):
            print("[IdaRestConfiguration::load_configuration] loading project config file: {0}".format(self.PROJECT_CFG_FILE))
            try:
                f = open(self.PROJECT_CFG_FILE, "r")
                self.config.update(json.load(f))
                f.close()
                print("[IdaRestConfiguration::load_configuration] loaded project config file: {0}".format(self.PROJECT_CFG_FILE))
            except IOError:
                print("[IdaRestConfiguration::load_configuration] failed to load project config file, using global config")
            except Exception as e:
                print("[IdaRestConfiguration::load_configuration] failed to load project config file: {0}".format(str(e)))
   

