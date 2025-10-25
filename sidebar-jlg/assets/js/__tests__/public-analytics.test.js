/* eslint-disable no-console */
describe('public analytics queue management', () => {
    const BASE_CONFIG = {
        endpoint: '/collect',
        nonce: 'nonce',
        action: 'action',
    };

    beforeEach(() => {
        jest.resetModules();
        jest.useFakeTimers();

        window.sidebarAnalyticsConfig = Object.assign({}, BASE_CONFIG);
        window.addEventListener = jest.fn();
        window.removeEventListener = jest.fn();
        delete navigator.sendBeacon;
    });

    afterEach(() => {
        jest.useRealTimers();
        delete window.sidebarJLGAnalyticsFactory;
        delete window.sidebarAnalyticsConfig;
        delete window.fetch;
    });

    function loadAnalyticsModule() {
        jest.isolateModules(() => {
            require('../public-analytics.js');
        });
    }

    it('drops the oldest event when the queue limit is exceeded', () => {
        const warnSpy = jest.spyOn(console, 'warn').mockImplementation(() => {});

        window.fetch = jest.fn(() => new Promise(() => {}));

        loadAnalyticsModule();

        const analytics = window.sidebarJLGAnalyticsFactory({ debug: true });

        for (let index = 0; index < 101; index += 1) {
            analytics.dispatch(`event-${index}`, { index });
        }

        expect(warnSpy).toHaveBeenCalledTimes(1);
        const [message, droppedEvent] = warnSpy.mock.calls[0];
        expect(message).toContain('dropping oldest analytics event');
        expect(droppedEvent.type).toBe('event-0');
        expect(typeof droppedEvent.context).toBe('string');

        const parsedContext = JSON.parse(droppedEvent.context);
        expect(parsedContext.index).toBe(0);

        warnSpy.mockRestore();
    });

    it('purges the queue and suspends processing after repeated failures', async () => {
        const stateChange = jest.fn();
        window.fetch = jest.fn(() => Promise.reject(new Error('network error')));

        loadAnalyticsModule();

        const analytics = window.sidebarJLGAnalyticsFactory({ onQueueStateChange: stateChange });

        analytics.dispatch('test-event', { attempt: 1 });

        await Promise.resolve();

        let delay = 1000;
        for (let attempt = 1; attempt < 5; attempt += 1) {
            await jest.advanceTimersByTimeAsync(delay);
            await Promise.resolve();
            delay = Math.min(delay * 2, 15000);
        }

        expect(stateChange).toHaveBeenCalledWith(expect.objectContaining({
            status: 'suspended',
            reason: 'consecutive-failures',
        }));

        expect(window.fetch).toHaveBeenCalledTimes(5);

        await jest.advanceTimersByTimeAsync(30000);
        await Promise.resolve();

        expect(stateChange).toHaveBeenCalledWith(expect.objectContaining({ status: 'resumed' }));
        expect(window.fetch).toHaveBeenCalledTimes(5);
    });
});
