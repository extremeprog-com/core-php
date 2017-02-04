BrowserCommutator = {
    /**
     * @function
     *
     *
     * @param {Object} event
     */
    handleSuccessEventsOnRequests: function(event) {
        var name  = event._event.replace('_Success', '') + event._reqid;

        this.waitingRequests[name].request._handled = true;
        this.waitingRequests[name].success(event.result);
        delete this.waitingRequests[name];
    }
    /**
     * @function
     *
     *
     * @param {Object} event
     */
    , handleFailEventsOnRequests: function(event) {
        var name  = event._event.replace('_Fail', '') + event._reqid;

        this.waitingRequests[name].request._handled = true;
        this.waitingRequests[name].fail();
        delete this.waitingRequests[name];
    }
    /**
     * @function
     *
     */
    , runCommutator: function() {
        var _this = this;

        var options = {protocols_whitelist: ['websocket'], debug: false, jsessionid: false};

        this.conn = new SockJS(_this.url, null, options);
        this.conn.onopen    = function()  {
            FireEvent(new _this.Start(), { Commutator: { connection: _this.conn } });
            _this.state.go('Connected');
        };
        this.conn.onmessage = function(e) {
            var message = JSON.parse(e.data);

            if( message instanceof Array ) {
                var objects = Core.AjaxInterface.parseMessage(message[0]);

                for( var i = 0; i < objects.length; i++ ) if( objects[i]._request ) {
                    _this.waitingRequests[objects[i]._request + objects[i]._reqid] = _this.conn;
                }
                Core.AjaxInterface.processParsedMessage(objects, { Commutator: { connection: _this.conn }, __proto__: message[1]});
            } else {
                _this.handle_internal_message(_this.conn, message);
            }
        };
        this.conn.onclose   = function()  { setTimeout(function() {
            _this.runCommutator();
            _this.state.go('Disconnected');
            FireEvent(new _this.ConnectionLost());
        }, 1000) };
    }
    /**
     * @function
     * Подписка на события ("Я слушаю следующие события")
     *
     * @param {String|Array} events
     * @param {Object} context
     */
    , subscribeEvents: function(events, context) {
        this.conn.send(JSON.stringify({
              action : 'subscribeEvents'
            , events : events
            , context: context
        }));
    }
    /**
     * @function
     * Подписка на реквесты ("Я обрабатываю следующие реквесты")
     *
     * @param {String|Array} requests
     * @param {Object} context
     */
    , subscribeRequests: function(requests, context) {
        this.conn.send(JSON.stringify({
              action  : 'subscribeRequests'
            , requests: requests
            , context : context
        }));
    }
    /**
     * @function
     * Обработка входящих сообщений о подписке на события или реквесты
     *
     * @param {Object} conn
     * @param {Object} msg
     */
    , handle_internal_message: function(conn, msg) {
        switch( msg.action ) {
            case 'subscribeEvents'  : this.subscribe_other_side(conn, msg.events,   msg.context); break;
            case 'subscribeRequests': this.subscribe_other_side(conn, msg.requests, msg.context); break;
            default:
                throw new Error("invalid message from other side: " + JSON.stringify(msg));
        }
    }
    /**
     * @function
     * Построение таблицы обработчиков событий и реквестов
     *
     * @param {Object} conn
     * @param {String|Array} events
     * @param {Object} context
     */
    , subscribe_other_side: function(conn, events, context) {
        events = events instanceof Array ? events : [events];
        conn.subscriptions = conn.subscriptions || {};
        for( var i = 0; i < events.length; i++ ) {
            conn.subscriptions[events[i]] = conn.subscriptions[events[i]] || [];
            conn.subscriptions[events[i]].push(context || '*');
        }
    }
    /**
     * @function
     * Послать реквест на сервер
     *
     * @param {Array} request
     * @param {Object} context
     */
    , sendRequest: function(request, context) {
        if( this.conn.subscriptions[request._request] || this.conn.subscriptions['*'] ) {
            for( var j = 0, contexts = this.conn.subscriptions[request._request]; j < contexts.length; j++ ) {
                // если контекст совпадает с шаблоном - отправим сообщение
                if( contexts == '*' || Core.contextMatches(context, contexts[j]) ) {

                    context = context || {};

                    if( context.Commutator ) {
                        delete context.Commutator;
                    }
                    //
                    console.log(Core.AjaxInterface.createMessage(request))

                    this.conn.send(JSON.stringify([Core.AjaxInterface.createMessage(request), context]));
                    break;
                }
            }
        }
    }
    /**
     * @function
     * Послать ивент на сервер
     *
     * @param {Array} event
     * @param {Object} context
     */
    , sendEvent: function(event, context) {
        if( this.conn && ( this.conn.subscriptions[event._event] || this.conn.subscriptions['*'] ) ) {
            for( var j = 0, contexts = this.conn.subscriptions[event._event]; j < contexts.length; j++ ) {
                // если контекст совпадает с шаблоном - отправим сообщение
                if( contexts == '*' || Core.contextMatches(context, contexts[j]) ) {

                    context = context || {};

                    if( context.Commutator ) {
                        delete context.Commutator;
                    }

                    
                    this.conn.send(JSON.stringify([Core.AjaxInterface.createMessage(event), context]));
                    break;
                }
            }
        }
    }
};