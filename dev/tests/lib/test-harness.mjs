/**
 * Minimalist, Zero-Dependency Test Harness for E2E Tests
 */

class Expectation {
    constructor(actual, testContext) {
        this.actual = actual;
        this.testContext = testContext;
    }

    toBe(expected) {
        if (this.actual !== expected) {
            throw new Error(`Expected ${JSON.stringify(expected)} but received ${JSON.stringify(this.actual)}`);
        }
    }

    toEqual(expected) {
        const a = JSON.stringify(this.actual);
        const b = JSON.stringify(expected);
        if (a !== b) {
            throw new Error(`Expected deep equality:\nExpected: ${b}\nReceived: ${a}`);
        }
    }

    toBeCloseTo(expected, numDigits = 2) {
        const diff = Math.abs(this.actual - expected);
        const tolerance = Math.pow(10, -numDigits) / 2;
        if (diff > tolerance) {
            throw new Error(`Expected ${this.actual} to be close to ${expected} within tolerance ${tolerance}`);
        }
    }

    toContain(substr) {
        if (typeof this.actual === 'string') {
            if (!this.actual.includes(substr)) {
                throw new Error(`Expected string to contain "${substr}", but it was not found.`);
            }
        } else if (Array.isArray(this.actual)) {
            if (!this.actual.includes(substr)) {
                throw new Error(`Expected array to contain item ${JSON.stringify(substr)}`);
            }
        } else {
            throw new Error(`Cannot check toContain on type ${typeof this.actual}`);
        }
    }

    toMatch(regex) {
        if (!regex.test(this.actual)) {
            throw new Error(`Expected string to match ${regex}, received "${this.actual}"`);
        }
    }

    toBeNull() {
        if (this.actual !== null) {
            throw new Error(`Expected null, received ${JSON.stringify(this.actual)}`);
        }
    }

    toBeDefined() {
        if (this.actual === undefined) {
            throw new Error('Expected value to be defined, received undefined');
        }
    }

    toBeTruthy() {
        if (!this.actual) {
            throw new Error(`Expected truthy value, received ${JSON.stringify(this.actual)}`);
        }
    }

    toBeFalsy() {
        if (this.actual) {
            throw new Error(`Expected falsy value, received ${JSON.stringify(this.actual)}`);
        }
    }

    toBeGreaterThanOrEqual(val) {
        if (!(this.actual >= val)) {
            throw new Error(`Expected ${this.actual} >= ${val}`);
        }
    }

    toBeLessThanOrEqual(val) {
        if (!(this.actual <= val)) {
            throw new Error(`Expected ${this.actual} <= ${val}`);
        }
    }

    toThrow(optPattern) {
        if (typeof this.actual !== 'function') {
            throw new Error('toThrow expects actual to be a function');
        }
        let threw = false;
        let caughtErr = null;
        try {
            this.actual();
        } catch (err) {
            threw = true;
            caughtErr = err;
        }
        if (!threw) {
            throw new Error('Expected function to throw, but it did not throw.');
        }
        if (optPattern && !String(caughtErr.message || caughtErr).includes(optPattern)) {
            throw new Error(`Expected error containing "${optPattern}", got "${caughtErr.message}"`);
        }
    }
}

class TestSuite {
    constructor() {
        this.suites = [];
        this.currentSuite = null;
        this.totalCount = 0;
        this.passCount = 0;
        this.failCount = 0;
        this.startTime = Date.now();
    }

    describe(name, fn) {
        const suite = { name, tests: [], passed: 0, failed: 0 };
        this.suites.push(suite);
        const prev = this.currentSuite;
        this.currentSuite = suite;
        try {
            fn();
        } finally {
            this.currentSuite = prev;
        }
    }

    async describeAsync(name, fn) {
        const suite = { name, tests: [], passed: 0, failed: 0 };
        this.suites.push(suite);
        const prev = this.currentSuite;
        this.currentSuite = suite;
        try {
            await fn();
        } finally {
            this.currentSuite = prev;
        }
    }

    test(name, fn) {
        const testCase = { name, fn };
        if (this.currentSuite) {
            this.currentSuite.tests.push(testCase);
        } else {
            this.describe('Default Suite', () => {
                this.currentSuite.tests.push(testCase);
            });
        }
    }

    expect(actual) {
        return new Expectation(actual, this);
    }

    async run() {
        this.startTime = Date.now();
        console.log('='.repeat(70));
        console.log(' WOW THAI PLATFORM & WOW EXPRESS — E2E TEST RUNNER');
        console.log('='.repeat(70));

        for (const suite of this.suites) {
            console.log(`\n▶ [SUITE] ${suite.name}`);
            for (const t of suite.tests) {
                this.totalCount++;
                try {
                    await t.fn();
                    this.passCount++;
                    suite.passed++;
                    console.log(`   ✅ PASS: ${t.name}`);
                } catch (err) {
                    this.failCount++;
                    suite.failed++;
                    console.log(`   ❌ FAIL: ${t.name}`);
                    console.log(`      Error: ${err.message}`);
                    if (err.stack) {
                        const lines = err.stack.split('\n').slice(1, 4).join('\n');
                        console.log(`      ${lines}`);
                    }
                }
            }
        }

        const duration = ((Date.now() - this.startTime) / 1000).toFixed(2);
        console.log('\n' + '='.repeat(70));
        console.log(` E2E TEST EXECUTION SUMMARY (${duration}s)`);
        console.log('='.repeat(70));
        console.log(` Total Tests: ${this.totalCount}`);
        console.log(` Passed:      ${this.passCount}`);
        console.log(` Failed:      ${this.failCount}`);
        console.log(` Pass Rate:   ${((this.passCount / (this.totalCount || 1)) * 100).toFixed(1)}%`);
        console.log('='.repeat(70));

        return {
            total: this.totalCount,
            passed: this.passCount,
            failed: this.failCount,
            duration,
            suites: this.suites
        };
    }
}

export const harness = new TestSuite();
export const describe = (name, fn) => harness.describe(name, fn);
export const describeAsync = (name, fn) => harness.describeAsync(name, fn);
export const test = (name, fn) => harness.test(name, fn);
export const expect = (actual) => harness.expect(actual);
