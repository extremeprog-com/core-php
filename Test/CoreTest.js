Core.registerRequestPoint("CoreTest_GoStateRq");
Core.registerRequestPoint("CoreTest_CheckStateRq");
Core.registerEventPoint("CoreTest_NextStep");
Core.registerEventPoint("CoreTest_Alert");
Core.registerEventPoint("CoreTest_TestDone");

CoreTest = {
    state: null,
    //{
    //    idx: 0,
    //    test_class: '',
    //    test_method: ''
    //},
    set autorun(param) {
        localStorage.test_autorun = JSON.stringify(param);
        if(!param) {
            this.state = {};
            this.saveState();
        }
    },
    get autorun() {
        return JSON.parse(localStorage.test_autorun || 'false');
    },
    Chain: function() {
        var test = Array.prototype.slice.call(arguments, 0);
        test.is_test = true;
        return test
    },
    loadState: function() {
        var event = CatchEvent(DOM_Init);

        try {
            this.state = this.state || JSON.parse(localStorage.testState);
        } catch(e) {
            this.state = {};
            this.saveState();
        }
    },
    runAutoloaded: function() {
        var event = CatchEvent(DOM_Init);

        var _this = this;

        if(this.autorun === true && !(this.state.queue && this.state.queue.length) && !this.state.test_class ) {
            delete _this.state.alerts;
            for(var classname in global) {
                if(classname.match(/^[A-Z]/) && global.hasOwnProperty(classname)) {
                    for(var methodname in global[classname]) {
                        if(global[classname].hasOwnProperty(methodname) && global[classname][methodname] && global[classname][methodname].is_test) {
                            if(!this.state) {
                                this.state = {};
                            }
                            if(!this.state.queue) {
                                this.state.queue = [];
                            }
                            console.log('queue add', [classname, methodname])
                            this.state.queue.push([classname, methodname])
                        }
                    }
                }
            }
        }

    },
    runReloaded: function() {
        var event = CatchEvent(DOM_Init, CoreTest_TestDone);

        setImmediate(function() {
            // восстанавливаемся после перезагрузки
            if(!this.state.test_class && this.state.queue && this.state.queue.length && event instanceof DOM_Init) {
                console.log(this.state.queue.join(','));
                var item = this.state.queue.shift();
                console.log('queue shift', item.join(':'), this.state.queue.join(','));
                this.state.test_class = item[0];
                this.state.test_method = item[1];
            }
            if(this.state.test_class && this.state.test_method) {
                this.run(this.state.test_class, this.state.test_method);
            }
        }.bind(this));

    },
    displayTestState: function() {
        var event = CatchEvent(CoreTest_NextStep, CoreTest_Alert, CoreTest_TestDone);

        var _this = this;

        var a = document.getElementById('__CoreTestInfo');
        if(!a) {
            a = document.createElement('div');
            a.id = '__CoreTestInfo';
            document.body.appendChild(a);
            a.style.cssText = 'position: fixed; right: 0; top: 0; /*max-width: 50%;*/ line-height: 12px; background: rgba(255,255,255,0.5); color: black; font-size: 10px; padding: 1px 5px; z-index: 9999;'
        }
        if(!(event instanceof CoreTest_TestDone)) {
            a.innerHTML =
                'Running test '
                + this.state.test_class
                + ':' + this.state.test_method
                + ' stage ' + this.state.idx
                + ' <img style="position: relative; top: 2px;" src="data:image/gif;base64,R0lGODlhEAAQAPIAAP///wAAAMLCwkJCQgAAAGJiYoKCgpKSkiH/C05FVFNDQVBFMi4wAwEAAAAh/hpDcmVhdGVkIHdpdGggYWpheGxvYWQuaW5mbwAh+QQJCgAAACwAAAAAEAAQAAADMwi63P4wyklrE2MIOggZnAdOmGYJRbExwroUmcG2LmDEwnHQLVsYOd2mBzkYDAdKa+dIAAAh+QQJCgAAACwAAAAAEAAQAAADNAi63P5OjCEgG4QMu7DmikRxQlFUYDEZIGBMRVsaqHwctXXf7WEYB4Ag1xjihkMZsiUkKhIAIfkECQoAAAAsAAAAABAAEAAAAzYIujIjK8pByJDMlFYvBoVjHA70GU7xSUJhmKtwHPAKzLO9HMaoKwJZ7Rf8AYPDDzKpZBqfvwQAIfkECQoAAAAsAAAAABAAEAAAAzMIumIlK8oyhpHsnFZfhYumCYUhDAQxRIdhHBGqRoKw0R8DYlJd8z0fMDgsGo/IpHI5TAAAIfkECQoAAAAsAAAAABAAEAAAAzIIunInK0rnZBTwGPNMgQwmdsNgXGJUlIWEuR5oWUIpz8pAEAMe6TwfwyYsGo/IpFKSAAAh+QQJCgAAACwAAAAAEAAQAAADMwi6IMKQORfjdOe82p4wGccc4CEuQradylesojEMBgsUc2G7sDX3lQGBMLAJibufbSlKAAAh+QQJCgAAACwAAAAAEAAQAAADMgi63P7wCRHZnFVdmgHu2nFwlWCI3WGc3TSWhUFGxTAUkGCbtgENBMJAEJsxgMLWzpEAACH5BAkKAAAALAAAAAAQABAAAAMyCLrc/jDKSatlQtScKdceCAjDII7HcQ4EMTCpyrCuUBjCYRgHVtqlAiB1YhiCnlsRkAAAOwAAAAAAAAAAADxiciAvPgo8Yj5XYXJuaW5nPC9iPjogIG15c3FsX3F1ZXJ5KCkgWzxhIGhyZWY9J2Z1bmN0aW9uLm15c3FsLXF1ZXJ5Jz5mdW5jdGlvbi5teXNxbC1xdWVyeTwvYT5dOiBDYW4ndCBjb25uZWN0IHRvIGxvY2FsIE15U1FMIHNlcnZlciB0aHJvdWdoIHNvY2tldCAnL3Zhci9ydW4vbXlzcWxkL215c3FsZC5zb2NrJyAoMikgaW4gPGI+L2hvbWUvYWpheGxvYWQvd3d3L2xpYnJhaXJpZXMvY2xhc3MubXlzcWwucGhwPC9iPiBvbiBsaW5lIDxiPjY4PC9iPjxiciAvPgo8YnIgLz4KPGI+V2FybmluZzwvYj46ICBteXNxbF9xdWVyeSgpIFs8YSBocmVmPSdmdW5jdGlvbi5teXNxbC1xdWVyeSc+ZnVuY3Rpb24ubXlzcWwtcXVlcnk8L2E+XTogQSBsaW5rIHRvIHRoZSBzZXJ2ZXIgY291bGQgbm90IGJlIGVzdGFibGlzaGVkIGluIDxiPi9ob21lL2FqYXhsb2FkL3d3dy9saWJyYWlyaWVzL2NsYXNzLm15c3FsLnBocDwvYj4gb24gbGluZSA8Yj42ODwvYj48YnIgLz4KPGJyIC8+CjxiPldhcm5pbmc8L2I+OiAgbXlzcWxfcXVlcnkoKSBbPGEgaHJlZj0nZnVuY3Rpb24ubXlzcWwtcXVlcnknPmZ1bmN0aW9uLm15c3FsLXF1ZXJ5PC9hPl06IENhbid0IGNvbm5lY3QgdG8gbG9jYWwgTXlTUUwgc2VydmVyIHRocm91Z2ggc29ja2V0ICcvdmFyL3J1bi9teXNxbGQvbXlzcWxkLnNvY2snICgyKSBpbiA8Yj4vaG9tZS9hamF4bG9hZC93d3cvbGlicmFpcmllcy9jbGFzcy5teXNxbC5waHA8L2I+IG9uIGxpbmUgPGI+Njg8L2I+PGJyIC8+CjxiciAvPgo8Yj5XYXJuaW5nPC9iPjogIG15c3FsX3F1ZXJ5KCkgWzxhIGhyZWY9J2Z1bmN0aW9uLm15c3FsLXF1ZXJ5Jz5mdW5jdGlvbi5teXNxbC1xdWVyeTwvYT5dOiBBIGxpbmsgdG8gdGhlIHNlcnZlciBjb3VsZCBub3QgYmUgZXN0YWJsaXNoZWQgaW4gPGI+L2hvbWUvYWpheGxvYWQvd3d3L2xpYnJhaXJpZXMvY2xhc3MubXlzcWwucGhwPC9iPiBvbiBsaW5lIDxiPjY4PC9iPjxiciAvPgo8YnIgLz4KPGI+V2FybmluZzwvYj46ICBteXNxbF9xdWVyeSgpIFs8YSBocmVmPSdmdW5jdGlvbi5teXNxbC1xdWVyeSc+ZnVuY3Rpb24ubXlzcWwtcXVlcnk8L2E+XTogQ2FuJ3QgY29ubmVjdCB0byBsb2NhbCBNeVNRTCBzZXJ2ZXIgdGhyb3VnaCBzb2NrZXQgJy92YXIvcnVuL215c3FsZC9teXNxbGQuc29jaycgKDIpIGluIDxiPi9ob21lL2FqYXhsb2FkL3d3dy9saWJyYWlyaWVzL2NsYXNzLm15c3FsLnBocDwvYj4gb24gbGluZSA8Yj42ODwvYj48YnIgLz4KPGJyIC8+CjxiPldhcm5pbmc8L2I+OiAgbXlzcWxfcXVlcnkoKSBbPGEgaHJlZj0nZnVuY3Rpb24ubXlzcWwtcXVlcnknPmZ1bmN0aW9uLm15c3FsLXF1ZXJ5PC9hPl06IEEgbGluayB0byB0aGUgc2VydmVyIGNvdWxkIG5vdCBiZSBlc3RhYmxpc2hlZCBpbiA8Yj4vaG9tZS9hamF4bG9hZC93d3cvbGlicmFpcmllcy9jbGFzcy5teXNxbC5waHA8L2I+IG9uIGxpbmUgPGI+Njg8L2I+PGJyIC8+Cg=="/>'
                //+ (_this.state.alerts ? '<span style="color: red;">alerts: ' + _this.state.alerts + '</span>' : '');
            ;
        } else {
            a.innerHTML = event.fail ? '<font color=red>Test fail: ' + event.fail + '</font>' : 'Tests done';
        }
    },
    saveState: function() {
        localStorage.testState = JSON.stringify(this.state);
    },

    run: function(test_class, test_method) {

        var _this = this;

        console.log('run test', test_class, test_method);

        if(!this.state.idx){
            this.state.idx = 0;
        }

        var TestStep = {
            title: function(title) {
                console.log(title);
            }
            , done: function(result) {

                last_step_to = -1;

                parse_result(result, function(data) {

                    CoreTest.state.idx++;

                    if(window[test_class][test_method][CoreTest.state.idx]) {
                        (data && data.wait_reload) || run_step();
                    } else {
                        console.log('Test ' + test_class + '.' + test_method +' success!');
                        //alert('Test completed successfully!');
                        delete _this.state.test_class;
                        delete _this.state.test_method;
                        delete _this.state.idx;
                        _this.saveState();
                        FireEvent(new CoreTest_TestDone({ test_class: test_class, test_method: test_method}));
                    }
                });
            }
            , fail: function(data) {
                CoreTest.testState = {};
                setImmediate(function(){
                    var test_class  = _this.state.test_class ;
                    var test_method = _this.state.test_method;

                    delete _this.state.test_class;
                    delete _this.state.test_method;
                    delete _this.state.idx;
                    _this.saveState();
                    FireEvent(new CoreTest_TestDone({ test_class: test_class, test_method: test_method, fail: data}));
                });
                console.error(data);
                //alert(data);
            }
        };

        function parse_result(result, done) {
            var matched_rules = 0;
            if(typeof result == 'object') {
                if(result.require_state) {
                    FireRequest(new CoreTest_GoStateRq(result.require_state), done);
                    matched_rules++;
                }
                var t0 = new Date;
                var timeout_alert = false;
                if(!result.wait_timeout) {
                    result.wait_timeout = 5
                }
                if(result.wait) {
                    setTimeout(done, result.wait);
                    matched_rules++;
                }
                if(result.wait_function) {
                    (function wait(){
                        if(result.wait_function()) {
                            done()
                        } else if(new Date - t0 < result.wait_timeout * 1000) {
                            setTimeout(wait, 200);
                        } else {
                            TestStep.fail('Timeout ' + result.wait_timeout + ' sec for wait_function', result.wait_function)
                        }
                    })();
                    matched_rules++;
                }
                if(result.wait_selector) {
                    (function wait(){
                        var el;
                        if((el = document.querySelectorAll(result.wait_selector) || []).length >= (result.min_count ? result.min_count : 1) ) {
                            if(result.click) {
                                var border = el[0].style.border, stage = 0;
                                setTimeout(function animate() {
                                    if(stage == 2) {
                                        el[0].click();
                                        done();
                                    } else {
                                        el[0].style.border = stage++ % 2 == 0 ? 'solid 3px red' : border;
                                        setTimeout(animate, 200);
                                    }
                                }, 1200);
                            } else {
                                done()
                            }
                        } else if(new Date - t0 < result.wait_timeout * 1000) {
                            if(!timeout_alert && result.wait_timeout_alert && new Date - t0 < result.wait_timeout_alert * 1000) {
                                console.warn('Timeout alert ' + result.wait_timeout_alert + ' sec for wait_selector ' + result.wait_selector + (result.min_count?' with count >=' + result.min_count: ''));
                                timeout_alert = true;
                                _this.state.alerts = (_this.state.alerts || 0) + 1;
                                FireEvent(new CoreTest_Alert);
                            }
                            setTimeout(wait, 200);
                        } else {
                            TestStep.fail('Timeout ' + result.wait_timeout + ' sec for wait_selector ' + result.wait_selector + (result.min_count?' with count >=' + result.min_count: ''))
                        }
                    })();
                    matched_rules++;
                }
                if(result.wait_selector_clear) {
                    (function wait(){
                        if(!document.querySelector(result.wait_selector_clear)) {
                            done()
                        } else if(new Date - t0 < result.wait_timeout * 1000) {
                            if(!timeout_alert && result.wait_timeout_alert && new Date - t0 < result.wait_timeout_alert * 1000) {
                                console.warn('Timeout alert ' + result.wait_timeout + ' sec for wait_selector_clear ' + result.wait_selector_clear );
                                timeout_alert = true;
                                _this.state.alerts = (_this.state.alerts || 0) + 1;
                                FireEvent(new CoreTest_Alert);
                            }
                            setTimeout(wait, 200);
                        } else {
                            TestStep.fail('Timeout ' + result.wait_timeout + ' sec for wait_selector_clear ' + result.wait_selector_clear)
                        }
                    })();
                    matched_rules++;
                }
                if(result.wait_text) {
                    (function wait(){
                        if(document.body.innerText.match(result.wait_text)) {
                            done()
                        } else if(new Date - t0 < result.wait_timeout * 1000) {
                            if(!timeout_alert && result.wait_timeout_alert && new Date - t0 < result.wait_timeout_alert * 1000) {
                                console.warn('Timeout alert ' + result.wait_timeout + ' sec for wait_text ' + result.wait_text );
                                timeout_alert = true;
                                _this.state.alerts = (_this.state.alerts || 0) + 1;
                                FireEvent(new CoreTest_Alert);
                            }
                            setTimeout(wait, 200);
                        } else {
                            TestStep.fail('Timeout ' + result.wait_timeout + ' sec for wait_text ' + result.wait_text)
                        }
                    })();
                    matched_rules++;
                }
                if( result.promote_state ) {
                    FireRequest(
                        new CoreTest_CheckStateRq({TestStep: TestStep, state: result.promote_state })
                    )
                }
                if(typeof result.replay_from_step !== 'undefined') {
                    CoreTest.state.idx = result.replay_from_step;
                    setTimeout(done,0);
                    matched_rules++;
                }
            }
            if(!matched_rules) {
                setTimeout(done,0);
            }
        }

        var last_step_to;

        function run_step() {

            FireEvent(new CoreTest_NextStep({
                test_class: test_class,
                test_method: test_method,
                idx: CoreTest.state.idx
            }));

            if (typeof last_step_to !== 'undefined' && last_step_to !== -1) {
                clearTimeout(last_step_to);
                last_step_to = undefined;
            }

            try {
                var step = window[test_class][test_method][CoreTest.state.idx], result;
                if(typeof step == 'object') {
                    // это настройка следуюшего шага
                    CoreTest.state.idx++;
                    run_step();
                    return;
                }
                if (typeof step == 'function') {
                    result = step(TestStep);
                } else {
                    TestStep.fail("Wrong step: " + CoreTest.state.idx);
                    return;
                }
                if(typeof result !== 'undefined') {
                    TestStep.done(result);
                } else {
                    if( last_step_to !== -1) {
                        last_step_to = setTimeout(function() {
                            TestStep.fail("Timeout on step: " + CoreTest.state.idx);
                        }, 5000)
                    } else {
                        last_step_to = undefined
                    }
                }
            } catch(e) {
                console.error(e);
                TestStep.fail('Test Fail on step ' + CoreTest.state.idx)
            }
        }

        run_step(TestStep);
    },
    reloadPage: function() {
        var request = CatchRequest(CoreTest_GoStateRq);

        var _this = this;

        if(request.reload || request.clear) {
            return function(success, fail) {
                setImmediate(function() {
                    if(request.clear) {
                        var test_autorun = localStorage.test_autorun;
                        var _disable_stat = localStorage._disable_stat;
                        localStorage.clear();
                        _this.saveState();
                        localStorage.test_autorun = test_autorun;
                        localStorage._disable_stat = _disable_stat;
                    }
                    if(request.reload) {
                        location.reload();
                    }
                    success({wait_reload: request.reload});
                    _this.saveState();
                });
            }
        }

    }

};

Core.processObject(CoreTest);