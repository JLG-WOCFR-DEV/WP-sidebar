(function(window) {
    'use strict';

    const baseConfig = typeof window.sidebarAnalyticsConfig === 'object' && window.sidebarAnalyticsConfig !== null
        ? window.sidebarAnalyticsConfig
        : {};

    function createNoopAnalytics() {
        return {
            enabled: false,
            dispatch: function noop() {},
        };
    }

    function isTruthy(value) {
        return value === true || value === '1' || value === 1 || value === 'true';
    }

    function buildBody(config, eventType, contextString) {
        const payload = {
            action: config.action,
            nonce: config.nonce,
            event_type: eventType,
            profile_id: config.profileId || config.profile_id || 'default',
            is_fallback: isTruthy(config.isFallback) || isTruthy(config.profile_is_fallback) ? '1' : '0',
        };

        if (contextString) {
            payload.context = contextString;
        }

        if (typeof URLSearchParams === 'function') {
            const params = new URLSearchParams();
            Object.keys(payload).forEach((key) => {
                if (typeof payload[key] !== 'undefined') {
                    params.append(key, payload[key]);
                }
            });
            return params.toString();
        }

        const encoded = [];
        Object.keys(payload).forEach((key) => {
            if (typeof payload[key] === 'undefined') {
                return;
            }
            encoded.push(encodeURIComponent(key) + '=' + encodeURIComponent(payload[key]));
        });

        return encoded.join('&');
    }

    function sendEvent(config, event, forceBeacon) {
        const body = buildBody(config, event.type, event.context);
        const supportsBeacon = typeof navigator !== 'undefined' && typeof navigator.sendBeacon === 'function';

        if (forceBeacon && supportsBeacon) {
            try {
                const blob = new Blob([body], { type: 'application/x-www-form-urlencoded; charset=UTF-8' });
                if (navigator.sendBeacon(config.endpoint, blob)) {
                    return Promise.resolve();
                }
            } catch (error) {
                // fall back to fetch below
            }
        }

        if (typeof fetch === 'function') {
            return fetch(config.endpoint, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8',
                },
                body,
                credentials: 'same-origin',
                keepalive: true,
            }).then((response) => {
                if (!response || !response.ok) {
                    throw new Error('Request failed');
                }
            });
        }

        if (supportsBeacon) {
            try {
                const blob = new Blob([body], { type: 'application/x-www-form-urlencoded; charset=UTF-8' });
                if (navigator.sendBeacon(config.endpoint, blob)) {
                    return Promise.resolve();
                }
            } catch (error) {
                return Promise.reject(error);
            }
        }

        return Promise.reject(new Error('No transport available'));
    }

    function createFactory(defaultConfig) {
        return function initializeAnalytics(runtimeConfig) {
            const config = Object.assign({}, defaultConfig, runtimeConfig || {});

            if (!config || !config.endpoint || !config.nonce || !config.action) {
                return createNoopAnalytics();
            }

            const MAX_QUEUE_SIZE = 100;
            const FAILURE_THRESHOLD = 5;
            const SUSPEND_DURATION = 30000;

            const queue = [];
            let sending = false;
            let retryDelay = 0;
            let retryTimeout = null;
            let consecutiveFailures = 0;
            let processingSuspended = false;
            let suspensionTimeout = null;

            const debugEnabled = isTruthy(config.debug);
            const stateChangeCallback = typeof config.onQueueStateChange === 'function'
                ? config.onQueueStateChange
                : null;

            function logDebugWarning(message, context) {
                if (!debugEnabled || typeof console === 'undefined' || typeof console.warn !== 'function') {
                    return;
                }

                if (typeof context !== 'undefined') {
                    console.warn(message, context);
                    return;
                }

                console.warn(message);
            }

            function notifyStateChange(state) {
                if (!stateChangeCallback) {
                    return;
                }

                try {
                    stateChangeCallback(state);
                } catch (error) {
                    logDebugWarning('sidebarJLGAnalyticsFactory: onQueueStateChange callback failed', error);
                }
            }

            function resumeProcessing() {
                if (!processingSuspended) {
                    return;
                }

                processingSuspended = false;
                suspensionTimeout = null;
                consecutiveFailures = 0;
                notifyStateChange({
                    status: 'resumed',
                    timestamp: Date.now(),
                });
                processQueue();
            }

            function suspendProcessing() {
                if (processingSuspended) {
                    return;
                }

                processingSuspended = true;
                notifyStateChange({
                    status: 'suspended',
                    reason: 'consecutive-failures',
                    attempts: consecutiveFailures,
                    timestamp: Date.now(),
                });

                if (suspensionTimeout) {
                    clearTimeout(suspensionTimeout);
                }

                suspensionTimeout = setTimeout(resumeProcessing, SUSPEND_DURATION);
            }

            function scheduleRetry() {
                if (retryTimeout) {
                    return;
                }

                retryDelay = retryDelay ? Math.min(retryDelay * 2, 15000) : 1000;
                retryTimeout = setTimeout(() => {
                    retryTimeout = null;
                    processQueue();
                }, retryDelay);
            }

            function resetRetry() {
                if (retryTimeout) {
                    clearTimeout(retryTimeout);
                    retryTimeout = null;
                }
                retryDelay = 0;
            }

            function processQueue() {
                if (sending || processingSuspended) {
                    return;
                }

                const nextEvent = queue[0];
                if (!nextEvent) {
                    return;
                }

                sending = true;

                sendEvent(config, nextEvent, false)
                    .then(() => {
                        queue.shift();
                        resetRetry();
                        consecutiveFailures = 0;
                    })
                    .catch(() => {
                        consecutiveFailures += 1;
                        scheduleRetry();
                        if (consecutiveFailures >= FAILURE_THRESHOLD) {
                            queue.splice(0, queue.length);
                            suspendProcessing();
                        }
                    })
                    .finally(() => {
                        sending = false;
                        if (!retryTimeout && queue.length > 0) {
                            processQueue();
                        }
                    });
            }

            function enqueue(eventType, context) {
                if (queue.length >= MAX_QUEUE_SIZE) {
                    const dropped = queue.shift();
                    logDebugWarning('sidebarJLGAnalyticsFactory: dropping oldest analytics event due to queue limit', dropped);
                }

                queue.push({ type: eventType, context });
                processQueue();
            }

            function normalizeContext(context) {
                if (!context || typeof context !== 'object') {
                    return undefined;
                }

                try {
                    const enriched = Object.assign({}, context, {
                        timestamp: Date.now(),
                    });
                    return JSON.stringify(enriched);
                } catch (error) {
                    return undefined;
                }
            }

            function flushWithBeacon() {
                if (!queue.length) {
                    return;
                }

                const pending = queue.splice(0, queue.length);
                consecutiveFailures = 0;
                if (processingSuspended) {
                    resumeProcessing();
                }
                pending.forEach((event) => {
                    sendEvent(config, event, true).catch(() => {
                        // swallow flush errors
                    });
                });
            }

            window.addEventListener('pagehide', flushWithBeacon);
            window.addEventListener('beforeunload', flushWithBeacon);

            return {
                enabled: true,
                dispatch(eventType, context) {
                    const contextString = normalizeContext(context);
                    enqueue(eventType, contextString);
                },
            };
        };
    }

    window.sidebarJLGAnalyticsFactory = createFactory(baseConfig);
})(window);
