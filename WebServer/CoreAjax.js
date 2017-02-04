/**
 * Crossdomain ajax
 * Using HTML5 XHR2 technology and JSONP (for old browsers)
 */

var Core = Core || {};


Core.registerEventPoint('CoreAjax_ServerError');

Core.registerEventPoint('CoreAjax_TimeoutError');

Core.Ajax = new (function(){
    var 
          id  = 0
        , xhr = new XMLHttpRequest();

    /**
     * @private
     * data {Object}
     *
    */
    function parseData(data) {
        var res = [];
        if( data instanceof Object ) {
            for( var i in data ) {
                if( data.hasOwnProperty(i) ) {
                    if( data[i] instanceof Object ) {
                        (function rs(pr, data) {
                            for (var i in data) {
                                if (data.hasOwnProperty(i)) {
                                    if (data[i] instanceof Object) {
                                        rs(pr + '[' + i + ']', data[i]);
                                    } else {
                                        res.push(pr + '[' + i + ']=' + encodeURIComponent(data[i]))
                                    }
                                }
                            }
                        })(i, data[i])
                    } else {
                        res.push(i + '=' + encodeURIComponent(data[i]));
                    }
                }
            }
        }
        return res.join('&');
    }

    if( xhr.withCredentials === undefined ) {
        var
              prefix = "__JSONP__"
            , document = window.document
            , documentElement = document.documentElement;

        return function(params) {
            var jsonp = "&" + ( params.jsonp || "jsonpcallback" );

            params.url += '?';
            params.url += parseData(params.data);

            function JSONPResponse() {
                try { delete window[src] } catch(e) {
                    window[src] = null;
                }
                documentElement.removeChild(script);
                if( typeof arguments[0] === 'string') params.success.apply(this, arguments);
                else {
                    arguments[0] = JSON.stringify(arguments[0]);
                    params.success.apply(this, arguments);
                }

            }
            var
                  src    = prefix + id++
                , script = document.createElement("script");

            window[src] = JSONPResponse;
            documentElement.insertBefore(
                script,
                documentElement.lastChild
            ).src = params.url + jsonp + "=" + src;
        }
    } else {
        return function(params) {
            var
                  data             = params.data
                , async            = ( params.async === undefined ) ? true : params.async
                , type             = params.type || "POST"
                , url              = params.url + (type=='GET' && data ? (params.url.indexOf('?')==-1?'?':'&') : '')
                , format           = params.format || 'text'
                , xhr              = new XMLHttpRequest()
                , error_callback   = params.error || function(){}
                , success_callback = params.success || null;

            if(navigator.appName.match(/Microsoft/) || navigator.userAgent.match(/Trident/)) {
                // падает сучка
                console.log('Fuck IE')
            } else {
                xhr.timeout = 7000;
            }

            if( type == 'GET' ) {
                url += parseData(data);
            }
            if( type == 'HEAD') {
                xhr.onreadystatechange = function() {
                    if (this.readyState == 4) {
                        if(this.status == 200) {
                            success_callback(this.status);
                        } else {
                            //FireEvent(new CoreAjax_ServerError({errmsg: this.status}));
                            error_callback(this.statusText);
                        }
                    }
                };
            } else {
                //при получении запроса, если он прошел успешно вызываем колбэк функцию и передаем ей полученные данные
                xhr.onreadystatechange = function(e) {

                    if( xhr.readyState != 4 ) return;

                    if( xhr.status == 200 ) {
                        success_callback(format == 'JSON' ? JSON.parse(this.responseText) : this.responseText);
                    } else {
                        if( xhr.statusText.length ) FireEvent(new CoreAjax_ServerError({errmsg: xhr.statusText}));
                        error_callback(xhr.statusText);
                    }
                };
            }

            xhr.onabort = function(e) {
                error_callback(e);
            };

            xhr.ontimeout = function() {
                FireEvent(new CoreAjax_TimeoutError({errmsg: 'Запрос превысил максимальное время ожидания.'}));
                error_callback('Запрос превысил максимальное время ожидания.');
            };

            switch( type ) {
                case "POST":
                    var t = JSON.stringify(data);
                    xhr.open("POST", url, async);
                    xhr.send(t);
                    break;
                case "GET":
                    xhr.open("GET",  url, async);
                    xhr.send();
                    break;
                case "HEAD":
                    xhr.open("HEAD", url, async);
                    xhr.send();
                    break;
                default:
                    console.log('error');
                    break;
            }

            return xhr;
        }
    }
})();

