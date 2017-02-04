if(typeof Core == 'undefined') Core = {};

Core.ObjectCache = Core.ObjectCache || {};
Core.AjaxInterface = function(url, transforms, add_params, method) {
    var
          _transforms     = transforms || {}
        , _url            = url || '';

    /**
     * Function _ajax
     * Ajax запрос на сервер
     *
     * @param {Object} transform алгоритм преобразования названия ивента с серверного в клиентское
     * @returns {Function}
     * @public
     */
    return function(data) {
        var s_cb, e_cb, xhr, po, el;

        data = data || {};

        for( var j in add_params ) {
            if( add_params.hasOwnProperty(j) ) {
                data[j] = add_params[j];
            }
        }

        for( var i in data ) {
            if( i.match(/^[A-Z]/) && data.hasOwnProperty(i) && data[i] instanceof Object && data[i]._self) {
                data[i] = data[i]._self;
            }
        }

        xhr = Core.Ajax({
              url: _url
            , data: data
            , type : method || "POST"
            , success: function(r) {
                if(method == 'HEAD') {
                    po = [];
                }
                po = Core.AjaxInterface.parseMessage(r?JSON.parse(r):[], _transforms);

                if(el) {
                    for(var i = 0; i < po.length; i++) {
                        if( po[i]._event && el[po[i]._event] ) {
                            for( var j = 0; j < el[po[i]._event].length; j++ ) {
                                el[po[i]._event][j](po[i]);
                            }
                        }
                    }
                } else {
                    // для удобства отладки
                    //if(!s_cb) {
                    //    console.log(po);
                    //}
                }
                if( s_cb instanceof Function ) s_cb(po);

                Core.AjaxInterface.processParsedMessage(po)
            }
            , error: function(er) {
                if( e_cb instanceof Function ) e_cb(er);
            }
        });
         
        return {
              error: function(handler) {
                e_cb = handler;
                return this;
            }
            , success: function(handler) {
                s_cb = handler;
                return this;
            }
            , cancel: function() {
                xhr.abort();
                return this;
            }
            , on: function(en, cb) {
                ((el = el || {})[en] = el[en] || []).push(cb);
                return this;
            }
        }

    };
};

Core.AjaxInterface.parseMessage = function(res, transform) {
    if( typeof res == 'string' ) {
        res = JSON.parse(res);
    }
    var objectCache = Core.ObjectCache;

    for( var i = 0; i < res.length; i++ ) {
        var o = res[i];

        if( o._self ) {
            // поищем объект в кэше и обновим, если он там есть
            // сохраним старую ссылку на объект
            if( objectCache[o._self] ) {
                // удаляем поля, которых нет у нового объекта
                var l, old = objectCache[o._self];
                for(l in old) {
                    if (old.hasOwnProperty(l) && !o.hasOwnProperty[l]) {
                        delete old[l];
                    }
                }
                for(l in o) {
                    if (o.hasOwnProperty(l)) {
                        old[l] = o[l];
                    }
                }
                res[i] = o = old;
            } else {
                objectCache[o._self] = o;
            }
        }

    }

    // перелинкуем все объекты
    for( var k = 0; k < res.length; k++ ) {
        var a = res[k];
        for( var n in a ) if( a.hasOwnProperty(n) && n.match(/^[A-Z]/)) {
            if( a[n] instanceof Array ) {
                for( var p = 0; p < a[n].length; p++ ) {

                    //if(typeof a[n][p] == 'object') console.error(n, p, JSON.stringify(a));

                    if( typeof objectCache[a[n][p]] !== 'undefined' ) {
                        a[n][p] = objectCache[a[n][p]];
                    } else {
                        a[n][p] = objectCache[a[n][p]] = {_self: a[n][p]};
                    }
                }
            } else if( !a[n] ) {
                // do nothing
            } else if( typeof objectCache[a[n]] != 'undefined') {
                a[n] = objectCache[a[n]];
            } else {
                a[n] = objectCache[a[n]] = {_self: a[n]};
            }
        }
    }

    // трансформируем
    for( var i = 0; i < res.length; i++ ) {
        var o = res[i];

        if( o._event ) {
            for( var ii in transform ) {
                o._event = o._event.replace(ii, transform[ii]);
            }
        }
        if( o._request ) {
            for( var ii in transform ) {
                o._request = o._request.replace(ii, transform[ii]);
            }
        }
    }

    return res;
};

Core.AjaxInterface.processParsedMessage = function(objects, context) {

    for( var i = 0; i < objects.length; i++ ) {
        var o = objects[i];

        if( o._event ) {
            if(typeof global[o._event] == 'undefined') {
                console.log('cannot handle event' + o._event)
            }
            FireEvent(new global[o._event](o), context);
        }
        if( o._request ) {
            if(typeof global[o._request] == 'undefined') {
                console.log('cannot handle event' + o._request)
            }
            FireRequest(new global[o._request](o), null, null, context);
        }
    }
    return objects;
};

Core.AjaxInterface.createMessage = function(object, target) {
    if( !target ) {
        target = [];
    }

    //console.error(object);

    (function serializeAndAdd(object) {
        for( var j = 0; j < target.length; j++ ) {
            if( target[j]._self == object._self ) return;
        }

        var obj = {};
        target.push(obj);

        for( var i in object ) {
            var field = object[i];
            if( field && typeof field == 'object' && field._self ) {
                obj[i] = field._self;
                serializeAndAdd(field)
            } else if (field && typeof field == 'object' && i.match(/^[A-Z]/) && field instanceof Array && field.length && field[0]._self) {
                obj[i] = [];
                for(var k = 0; k < field.length; k++) {
                    obj[i].push(field[k]._self);
                    //serializeAndAdd(field[k]);
                }
            } else if (field && typeof field == 'object' && (field['_event'] || field['_request'] )) {
//                throw new Error('HZ CHO DELATb');
            } else {
                obj[i] = field;
            }
        }
    })(object);

    //console.error(target);
    //
    return target;
};

if( typeof require != 'undefined' ) {
    module.exports = Core.AjaxInterface;
}