(function () {
    "use strict";
    var ThruwayObject;

    var thruwayApp = angular.module("thruway", []);

    thruwayApp.value("thruwaySettings", {
        url: "ws://demo.thruway.ws:9090",
        realm: 'drupalrealm'
    });

    thruwayApp.factory("$thruway", ["$q", "$parse", "$timeout", "thruwayIndex", "$rootScope", "drupalAuth", "$window", "thruwaySettings",
        function ($q, $parse, $timeout, thruwayIndex, $rootScope, drupalAuth, $window, thruwaySettings) {

            var connection, uris = [], session, callbackQueue = [], baseObject, settings;

            function onchallenge(session, method, extra) {
                if (method == 'drupal.' + settings.realm) {
                    return drupalAuth.authenticate(session);
                    //return drupalAuth.anonymous();

                } else {
                    console.log("don't know how to authentication using " + method);
                }
            }

            //Add authentication options
            settings = thruwaySettings;
            angular.extend(settings, {
                authmethods: ['drupal.' + settings.realm],
                onchallenge: onchallenge
            });

            connection = new autobahn.Connection(settings);

            connection.onclose = function (reason, details) {
                console.log('connection: ', reason);
                console.log('connection details: ', details);

                if (details.reason && details.reason == "bad.login") {
                    $window.localStorage.removeItem('token');
                    connection.open();
                }

            };

            connection.onopen = function (sess, details) {

                session = sess;
                $rootScope.$emit("thruway.open", details);

                baseObject = new ThruwayObject($q, session, thruwayIndex);

                //Call any callbacks that were queued up before the connection was established
                while (callbackQueue.length > 0) {
                    var call = callbackQueue.shift();
                    call.method.apply(call.object, call.args);
                }

                console.log('connected');

            };

            connection.open();

            /**
             * Build a key for the list placeholder based upon the uri and the args
             * @param uri
             * @param args
             * @returns {*}
             */
            var placeholderKey = function (uri, args) {
                args = JSON.stringify(args);
                args.replace(/\s+/g, '');
                return uri + args;
            };


            return function (uri) {

                uris.push(uri);

                return {
                    get: function (uuid) {

                        if (!session || session == 'undefined') {
                            console.log("session isn't up, so we're queuing");
                            callbackQueue.push({object: this, method: this.get, args: [uuid]})
                        } else {
                            session.call(uri + '.get', [uuid]).then(
                                function (item) {

                                    angular.extend(item, baseObject.construct(uri));
                                    thruwayIndex.update(item);
                                },
                                function (error) {
                                    console.log("Error when calling '" + self._uri + "':", error);
                                });

                        }

                        return thruwayIndex.get(uuid);

                    },
                    getList: function (args) {
                        args = args || {};
                        var uriParts = uri.split('.');
                        args.type = uriParts[2];

                        if (!session || session == 'undefined') {
                            console.log("session isn't up, so we're queuing");
                            callbackQueue.push({object: this, method: this.getList, args: [args]})
                        } else {
                            session.call(uri + ".getAll", [args]).then(
                                function (res) {
                                    if (!angular.isArray(res)) res = [res];
                                    angular.forEach(res, function (item, key) {
                                        angular.extend(res[key], baseObject.construct(uri));
                                    });
                                    var items = thruwayIndex.setList(placeholderKey(uri, args), res);
                                    angular.extend(items, baseObject.constructList(uri, placeholderKey(uri, args)));

                                    console.log("getAll Result:", res);
                                },
                                function (error) {
                                    console.log("Error when calling '" + self._uri + "':", error);
                                }
                            );
                        }

                        //Return the placeholder for this request
                        return thruwayIndex.getList(placeholderKey(uri, args));

                    },
                    create: function (entity) {
                        entity = entity || {};
                        var uriParts = uri.split('.');
                        entity.type = uriParts[2];

                    },
                    uri: function () {
                        return uri;
                    },
                    connection: function () {
                        return connection;
                    }
                };

            };
        }
    ]);

    thruwayApp.factory("thruwayIndex", ["$rootScope", "$timeout", "$parse",
        function ($rootScope, $parse, $watch) {

            var index = [];

            return {

                get: function (uuid) {
                    if (!index[uuid]) {
                        index[uuid] = {};
                    }
                    console.log("Got this from the index: ", index[uuid]);
                    console.log("The full index", index);
                    return index[uuid];
                },

                set: function (items) {
                    if (!angular.isArray(items)) {
                        items = [items];
                    }

                    var newItems = [];

                    angular.forEach(items, function (item) {

                        if (item.uuid[0].value) {
                            index[item.uuid[0].value] = item;
                            newItems.push(index[item.uuid[0].value]);
                        }

                    });
                    $rootScope.$apply();

                    console.log("Set items to index: ", newItems);
                    return newItems;
                },
                getList: function (key) {
                    if (!index[key]) {
                        index[key] = [];
                    }
                    console.log("Got this List from the index: ", index[key]);
                    return index[key];
                },

                setList: function (key, items) {
                    if (!angular.isArray(items)) items = [items];

                    if (!index[key]) {
                        index[key] = [];
                    }
                    var newItems = [];

                    angular.forEach(items, function (item) {

                        if (item.uuid && item.uuid[0] && item.uuid[0].value) {
                            index[item.uuid[0].value] = item;
                            newItems.push(index[item.uuid[0].value]);
                            //newItems[key] = index[item.uuid[0].value];
                        }

                    });

                    index[key] = angular.extend(index[key], newItems);

                    console.log("Set this List in the index: ", index[key]);
                    console.log("The full index", index);
                    $rootScope.$apply();
                    return index[key];
                },

                pushList: function (key, item) {

                    if (!index[key]) {
                        index[key] = [];
                    }
                    if (item.uuid && item.uuid[0] && item.uuid[0].value) {
                        index[item.uuid[0].value] = item;
                        index[key].push(index[item.uuid[0].value]);
                    }

                    console.log("Pushed to this List in the index: ", index[key]);
                    console.log("The full index", index);
                    $rootScope.$apply();
                    return index[key];
                },

                update: function (item) {
                    index[item.uuid[0].value] = index[item.uuid[0].value] || {};
                    angular.extend(index[item.uuid[0].value], item);
                    $rootScope.$apply();

                    return index[item.uuid[0].value];
                },

                remove: function (uuid) {
                    delete index[uuid];
                }
            };
        }
    ]);

    ThruwayObject = function ($q, session, thruwayIndex) {
        this._q = $q;
        this._session = session;
        this._index = thruwayIndex;
    };

    ThruwayObject.prototype = {
        construct: function (uri) {
            var self = this;
            var object = {};

            object.$save = function () {
                return object.$update(this);
            };

            object.$delete = function () {
                var deferred = self._q.defer();
                self._session.call(uri + '.remove', [clean(this)]).then(function () {
                        deferred.resolve();
                    },
                    function (error) {
                        console.log("Error when calling '" + uri + "':", error);
                    });
                return deferred.promise;
            };

            object.$update = function (newValue) {
                var deferred = self._q.defer();
                if (newValue === undefined) newValue = this;

                self._session.call(uri + '.update', [clean(newValue)]).then(function () {
                        deferred.resolve();
                    },
                    function (error) {
                        console.log("Error when calling '" + uri + "':", error);
                    });
                return deferred.promise;
            };

            object.$getRelated = function () {
                var parentEntity = this;
                var deferred = self._q.defer();


                self._session.call(uri + '.referencedEntities', [clean(parentEntity)]).then(function (res) {

                        console.log('related entities results: ', res);
                        angular.forEach(res, function (uri, uriKey) {

                            //add it to the index
                            angular.forEach(uri, function (items, uuid) {
                                angular.forEach(items, function (item, name) {

                                    thruwayIndex.set(uriKey, uuid, angular.extend(item, thruwayIndex.get(uriKey, uuid)));

                                    angular.forEach(parentEntity[name], function (i, k) {

                                        if (parentEntity[name][k] && parentEntity[name][k]['target_id'] == item.tid[0].value) {
                                            parentEntity[name][k] = angular.extend(parentEntity[name][k], thruwayIndex.get(uriKey, uuid));
                                        }


                                    });
                                    //Add the related entity to the parent item

                                });

                                //subscribe to updates for the related entities
                                if (self._session._subscriptions[uriKey + '.update'] == undefined) {
                                    self._session.subscribe(uriKey + '.update', function (res) {
                                        console.log("Update Related Result:", res);
                                        thruwayIndex.update(uriKey, res[0].uuid[0].value, res[0]);

                                    });
                                    console.log('session', self._session._subscriptions);
                                }


                            });
                            console.log('index for ' + uriKey, thruwayIndex.get(uriKey));
                        });

                        deferred.resolve();
                    },
                    function (error) {
                        console.log("Error when calling '" + uri + ".referenceEntities':", error);
                    });
                return deferred.promise;
            };

            self._object = object;

            /**
             * Subscribe to Update Events
             */
            self._session.subscribe(uri + '.update', function (res) {
                console.log("Update Result:", res);
                self._index.update(res[0]);

            });

            /**
             * Subscribe to New events
             */
            self._session.subscribe(uri + '.add', function (res) {
                console.log("New Result:", res);
                self._index.set(angular.extend(res[0], self._object));
            });


            /**
             * Subscribe to Remove events
             */
            self._session.subscribe(uri + '.remove', function (res) {
                console.log("Remove Result:", res);
                self._index.remove(res[0].uuid[0].value);
            });


            /**
             * Removed related entities and other clean up before we send the object back to the server
             * @param entity
             */
            var clean = function (entity) {

                var cleanedEntity = {};
                angular.forEach(entity, function (property, propertyName) {
                    if (property[0] && property[0]['target_id']) {
                        cleanedEntity[propertyName] = [{target_id: property[0]['target_id']}];
                    } else {
                        cleanedEntity[propertyName] = property;
                    }
                });

                return cleanedEntity;
            };

            return self._object;
        },
        constructList: function (uri, listKey) {
            var self = this;
            var object = {};

            object.$add = function (item) {
                item = item || {};
                var uriParts = uri.split('.');
                item.type = [{target_id: uriParts[2]}];

                var deferred = self._q.defer();
                if (item === undefined) item = this;
                self._session.call(uri + '.add', [item]).then(function (res) {
                        console.log("new result", res);
                        var o = new ThruwayObject(self._q, self._session, self._index).construct(uri);
                        angular.extend(res, o);
                        self._index.pushList(listKey, res);
                        deferred.resolve();
                    },
                    function (error) {
                        console.log("Error when calling '" + self._uri + "':", error);
                    });
                return deferred.promise;
            };

            /**
             * Subscribe to Remove events
             */
            self._session.subscribe(uri + '.remove', function (res) {
                console.log("Remove Result from list:", res);
                //Remove matches from the list;
                var list = self._index.getList(listKey);
                angular.forEach(list, function (item, key) {
                    if (item.uuid && item.uuid[0] && item.uuid[0].value && item.uuid[0].value == res.uuid[0].value) {
                        list.splice(key, 1);
                    }
                });

            });


            return object;
        }
    };


    /**
     * Authentication
     */
    thruwayApp.factory("drupalAuth", ["$q", "$rootScope", "$window", "$location", function ($q, $rootScope, $window, $location) {

        return {
            authenticate: function (session) {
                //check to see if we have a jwt token saved
                if ($window.localStorage.token) {
                    return $q.when($window.localStorage.token);
                }

                var deferred = $q.defer();
                var lastPath = $location.$$path;
                var loginInfo;

                $rootScope.$broadcast("thruway.auth", session);

                $rootScope.$on("thruway.login", function (event, message) {
                    loginInfo = message;
                    deferred.resolve(loginInfo);
                    $location.path(lastPath).replace();
                });

                $rootScope.$on("thruway.open", function (event, message) {
                    session.call("utils.utils.generateToken", [loginInfo.user, loginInfo.pass]).then(function (token) {
                        //save the token
                        $window.localStorage.token = token;
                    });
                });

                return deferred.promise;
            },
            anonymous: function () {
                return "anonymous";
            }
        };

    }]);
})();