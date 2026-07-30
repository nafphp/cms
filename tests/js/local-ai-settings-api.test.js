import assert from 'node:assert/strict';
import test from 'node:test';

import {
    normalizeReviewItems,
    parseModelJson,
    randomItem,
} from '../../public/js/admin/local-ai-settings/api.js';

/*
 * Both parsers consume output produced by a local model, so malformed input is
 * ordinary traffic rather than an exceptional case: they must degrade to an
 * empty result instead of throwing into the settings panel.
 */

test('parses plain JSON', () => {
    assert.deepEqual(parseModelJson('{"a":1}'), {a: 1});
});

test('parses JSON wrapped in a fenced code block', () => {
    assert.deepEqual(parseModelJson('```json\n{"a":1}\n```'), {a: 1});
    assert.deepEqual(parseModelJson('```\n{"a":1}\n```'), {a: 1});
});

test('parses JSON surrounded by prose', () => {
    assert.deepEqual(parseModelJson('Sure, here you go:\n{"a":1}\nHope that helps!'), {a: 1});
});

test('falls back to an empty object when there is no JSON', () => {
    // Callers read properties off the result unguarded, so the fallback is an
    // empty object rather than null on purpose.
    assert.deepEqual(parseModelJson('I am afraid I cannot do that.'), {});
    assert.deepEqual(parseModelJson(''), {});
    assert.deepEqual(parseModelJson(null), {});
});

test('falls back to an empty object for malformed JSON instead of throwing', () => {
    assert.deepEqual(parseModelJson('{"a": }'), {});
});

test('keeps review items that carry any usable field', () => {
    const items = normalizeReviewItems([
        {title: 'Wrong tool'},
        {problem: 'Picked the delete tool'},
        {proposed_solution: 'Ask first'},
        {proposed_memory: 'Never delete unprompted'},
    ]);

    assert.equal(items.length, 4);
    assert.equal(items[0].title, 'Wrong tool');
    assert.equal(items[2].solution, 'Ask first');
    assert.equal(items[3].proposedMemory, 'Never delete unprompted');
});

test('drops review items without any usable field', () => {
    assert.deepEqual(normalizeReviewItems([null, 'not an object', {}, {question: 'only a question'}]), []);
});

test('accepts both snake_case and camelCase source ids', () => {
    assert.deepEqual(normalizeReviewItems([{title: 'x', source_ids: ['a', '']}])[0].sourceIds, ['a']);
    assert.deepEqual(normalizeReviewItems([{title: 'x', sourceIds: ['b']}])[0].sourceIds, ['b']);
});

test('normalizing a non-array yields an empty array', () => {
    assert.deepEqual(normalizeReviewItems(null), []);
    assert.deepEqual(normalizeReviewItems('nope'), []);
    assert.deepEqual(normalizeReviewItems(undefined), []);
});

test('randomItem returns an element of the list', () => {
    const list = ['a', 'b', 'c'];
    for (let i = 0; i < 20; i++) {
        assert.ok(list.includes(randomItem(list)));
    }
});

test('randomItem returns an empty string for an empty or invalid list', () => {
    assert.equal(randomItem([]), '');
    assert.equal(randomItem(null), '');
});
