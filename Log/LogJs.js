var LogJs = {
    queue_interval: 5000,
    queue: [],
    _timeout: null,
    write: function(o) {
        if(!this._timeout) {
            this._timeout = setTimeout(function() {
                LogJs._send();
            }, this.queue_interval);
        }
        this.queue.push(JSON.stringify(o));
    },
    _send: function() {
        this._timeout = null;
        clearTimeout(this._timeout);
        jQuery.post("/_jsLog", {ls: this.queue});
        this.queue = [];
    }
};