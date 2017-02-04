ServerCommutator = {
    /**
     * @function
     *
     * @param {Object} event
     */
    handleSuccessEventsOnRequests: function(event) {
        var name  = event._event.replace('_Success', '') + event._reqid;

        try {
            this.waitingRequests[name].request._handled = true;
            this.waitingRequests[name].success(event.result);
            delete this.waitingRequests[name];
        } catch(e) {
            // console.log(e);
            // console.log(event);
            // console.log(this.waitingRequests);
        }
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

        this.sockjs_server.on('connection', function(conn) {
            _this.connections[conn.id] = conn;
            _this.connections[conn.id].subscriptions = {};

            conn.on('data', function(message) {
                message = JSON.parse(message);

                //console.log(message);

                if( message instanceof Array ) {
                    var objects = Core.AjaxInterface.parseMessage(message[0]);

                    for( var i = 0; i < objects.length; i++ ) if( objects[i]._request ) {
                        _this.waitingRequests[objects[i]._request + objects[i]._reqid] = conn;
                    }

                    Core.AjaxInterface.processParsedMessage(objects, { Commutator: { connection: conn }, __proto__: message[1] });
                } else {
                    _this.handle_internal_message(conn, message);
                }
            });
            conn.on('close', function() {
                delete _this.connections[conn.id]
            });
        });
    }
    /**
     * @function
     *
     * @param conn
     * @param events
     * @param context
     */
    , subscribeEvents: function(conn, events, context) {
        conn.write(JSON.stringify({
              action : 'subscribeEvents'
            , events : events
            , context: context
        }));
    }
    /**
     * @function
     *
     * @param conn
     * @param requests
     * @param context
     */
    , subscribeRequests: function(conn, requests, context) {
        conn.write(JSON.stringify({
              action  : 'subscribeRequests'
            , requests: requests
            , context : context
        }));
    }
    /**
     * @function
     *
     * @param conn
     * @param msg
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
     *
     * @param conn
     * @param events
     * @param context
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
     *
     * @param conn
     */
    , unsubscribe_other_side: function(conn) {
        for( var i in conn.subscriptions ) if( conn.subscriptions.hasOwnProperty(i) ) {
            delete conn.subscriptions[i];
        }
    }
    /**
     * @function
     *
     * @param request
     * @param context
     */
    , sendRequest: function(request, context) {
        for( var i in this.connections ) if( this.connections.hasOwnProperty(i) ) {
            if( this.connections[i].subscriptions[request._request] || this.connections[i].subscriptions['*'] ) {
                for( var j = 0, contexts = this.connections[i].subscriptions[request._request]; j < contexts.length; j++ ) {
                    // если контекст совпадает с шаблоном - отправим сообщение
                    if( contexts == '*' || Core.contextMatches(context, contexts[j]) ) {

                        context = context || {};

                        if( context.Commutator ) {
                            delete context.Commutator;
                        }

                        this.connections[i].write(JSON.stringify([Core.AjaxInterface.createMessage(request), context]));
                        break;
                    }
                }
            }
        }
    }
    /**
     * @function
     *
     * @param event
     * @param context
     */
    , sendEvent: function(event, context) {
        for( var i in this.connections ) if( this.connections.hasOwnProperty(i) ) {
            if( this.connections[i].subscriptions[event._event] || this.connections[i].subscriptions['*'] ) {
                for( var j = 0, contexts = this.connections[i].subscriptions[event._event]; j < contexts.length; j++ ) {
                    // если контекст совпадает с шаблоном - отправим сообщение
                    if( contexts == '*' || Core.contextMatches(context, contexts[j]) ) {
                        context = context || {};

                        if( context.Commutator ) {
                            delete context.Commutator;
                        }

                        this.connections[i].write(JSON.stringify([Core.AjaxInterface.createMessage(event), context]));
                        break;
                    }
                }
            }
        }
    }
    /**
     * @function
     *
     * @param event
     * @param context
     */
    , sendEventOnRequest: function(event, context) {
        context = context || {};
        if( context.Commutator ) delete context.Commutator;

        this.waitingRequests[event._event.replace(/_(Success|Fail)$/, '') + event._reqid].write(JSON.stringify([Core.AjaxInterface.createMessage(event), context]));
        delete this.waitingRequests[event._event.replace(/_(Success|Fail)$/, '') + event._reqid];
    }
};