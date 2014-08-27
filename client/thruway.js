(function () {
    "use strict";
    var ThruwayObject, DrupalAuth;

    angular.module("thruway", ['ngRoute']);

    angular.module("thruway").factory("$thruway", ["$q", "$parse", "$timeout", "thruwayIndex", "$rootScope", "drupalAuth", "$window",
        function ($q, $parse, $timeout, thruwayIndex, $rootScope, drupalAuth, $window) {

            var connectionPromise, connection, uris = [];

            function onchallenge(session, method, extra) {
                if (method == 'drupal.drupalrealmdave') {
                    return drupalAuth.authenticate(session);
                    //return drupalAuth.anonymous();


                } else {
                    console.log("don't know how to authentication using " + method);
                }
            }

            // @todo make this configurable
            connection = new autobahn.Connection({
                url: "ws://127.0.0.1:9090",
                realm: 'drupalrealmdave',
                authmethods: ['drupal.drupalrealmdave'],
                onchallenge: onchallenge
            });

            connection.open();

            connection.onclose = function (reason, details) {

                console.log('connection: ', reason);
                console.log('connection details: ', details);

                if (details.reason && details.reason == "bad.login") {
                    $window.localStorage.removeItem('token');
                    connection.open();
                }

            };

            return function (uri, args) {
                args = args || {};
                connection.onopen = function (session, details) {
                    $rootScope.$emit("thruway.open", details);

                    console.log('connected');
                    angular.forEach(uris, function (uri) {
                        var ts = new ThruwayObject($q, $parse, $timeout, uri, args, session);
                        var object = ts.construct(thruwayIndex);
                        $rootScope.$apply(function () {
                            angular.extend(thruwayIndex.get(uri), object);
                        });
                    });
                };
                uris.push(uri);
                return thruwayIndex.get(uri);
            };
        }
    ]);

    angular.module("thruway").factory("thruwayIndex", ["$rootScope", "$timeout", "$parse",
        function ($rootScope, $parse, $watch) {

            var index = [];

            return {

                get: function (uri, key) {
                    index[uri] = index[uri] || {};
                    if (key) {
                        return index[uri][key];
                    } else {

                        return index[uri];
                    }
                },

                set: function (uri, key, item) {
                    var self = this;

                    index[uri] = index[uri] || {};

                    $rootScope.$apply(function () {
                        index[uri][key] = item;
                    });


//                    $rootScope.$watch(function () {
//                            return index[uri][key];
//                        },
//                        function (newVal, oldVal) {
//
//                            if (JSON.stringify(oldVal) != JSON.stringify(newVal)) {
//
//                                console.log('updating item', item);
//                                if (item && item.hasOwnProperty('$update')) item.$update();
//
//                            }
//
//                        }, true);


                },
                update: function (uri, key, item) {
                    index[uri] = index[uri] || {};

                    if (index[uri][key] !== undefined) {
                        angular.extend(index[uri][key], item);
                        $rootScope.$apply();

                    } else {
                        //@todo if we're getting an update for an item that we're not tracking, we should probably make a request for the item
                        console.log("Can't update item since we're not tracking it: ", item);
                    }
                },

                remove: function (uri, key) {
                    delete index[uri][key];
                    $rootScope.$apply();
                }
            };
        }
    ]);

    ThruwayObject = function ($q, $parse, $timeout, uri, args, session) {
        this._q = $q;
        this._parse = $parse;
        this._timeout = $timeout;
        this._uri = uri;
        this._args = args;
        this._session = session;

    };

    ThruwayObject.prototype = {
        construct: function (thruwayIndex) {
            var self = this;
            var object = {};

            object.$save = function () {
                return object.$update(this);
            };

            object.$add = function (item) {
                var deferred = self._q.defer();
                if (item === undefined) item = this;
                self._session.call(self._uri + '.add', [clean(item)]).then(function (res) {
                        console.log("new result", res);
                        thruwayIndex.set(self._uri, res[0].uuid[0].value, angular.extend(res[0], self._object));
                        deferred.resolve();
                    },
                    function (error) {
                        console.log("Error when calling '" + self._uri + "':", error);
                    });
                return deferred.promise;
            };

            object.$remove = function () {
                var deferred = self._q.defer();
                self._session.call(self._uri + '.remove', [clean(this)]).then(function () {
                        deferred.resolve();
                    },
                    function (error) {
                        console.log("Error when calling '" + self._uri + "':", error);
                    });
                return deferred.promise;
            };

            object.$update = function (newValue) {
                var deferred = self._q.defer();
                if (newValue === undefined) newValue = this;

                self._session.call(self._uri + '.update', [clean(newValue)]).then(function () {
                        deferred.resolve();
                    },
                    function (error) {
                        console.log("Error when calling '" + self._uri + "':", error);
                    });
                return deferred.promise;
            };

            object.$getRelated = function () {
                var parentEntity = this;
                var deferred = self._q.defer();


                self._session.call(self._uri + '.referencedEntities', [clean(parentEntity)]).then(function (res) {

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
                        console.log("Error when calling '" + self._uri + ".referenceEntities':", error);
                    });
                return deferred.promise;
            };


            self._object = object;

            var uriParts = self._uri.split('.');
            self._args.type = uriParts[2];

            /**
             * Get the initial value for this call
             */
            self._session.call(self._uri + ".getAll", [self._args]).then(
                function (res) {
                    console.log("Result:", res);
                    angular.forEach(res, function (item) {
                        thruwayIndex.set(self._uri, item.uuid[0].value, angular.extend(item, self._object));
                    });
                    console.log('index for ' + self._uri, thruwayIndex.get(self._uri));
                },
                function (error) {
                    console.log("Error when calling '" + self._uri + "':", error);
                }
            );

            /**
             * Subscribe to Update Events
             */
            self._session.subscribe(self._uri + '.update', function (res) {
                console.log("Update Result:", res);
                thruwayIndex.update(self._uri, res[0].uuid[0].value, res[0]);

            });

            /**
             * Subscribe to New events
             */
            self._session.subscribe(self._uri + '.add', function (res) {
                console.log("New Result:", res);
                thruwayIndex.set(self._uri, res[0].uuid[0].value, angular.extend(res[0], self._object));
            });


            /**
             * Subscribe to Remove events
             */
            self._session.subscribe(self._uri + '.remove', function (res) {
                console.log("Remove Result:", res);
                thruwayIndex.remove(self._uri, res[0].uuid[0].value);
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
        }
    };

    angular.module("thruway").factory("drupalAuth", ["$q", "$rootScope", "$window", "$location", function ($q, $rootScope, $window, $location) {

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