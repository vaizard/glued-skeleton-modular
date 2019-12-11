function _slicedToArray(arr, i) { return _arrayWithHoles(arr) || _iterableToArrayLimit(arr, i) || _nonIterableRest(); }

function _nonIterableRest() { throw new TypeError("Invalid attempt to destructure non-iterable instance"); }

function _iterableToArrayLimit(arr, i) { var _arr = []; var _n = true; var _d = false; var _e = undefined; try { for (var _i = arr[Symbol.iterator](), _s; !(_n = (_s = _i.next()).done); _n = true) { _arr.push(_s.value); if (i && _arr.length === i) break; } } catch (err) { _d = true; _e = err; } finally { try { if (!_n && _i["return"] != null) _i["return"](); } finally { if (_d) throw _e; } } return _arr; }

function _arrayWithHoles(arr) { if (Array.isArray(arr)) return arr; }

var _global = global,
    describe = _global.describe,
    it = _global.it;
import expect from 'expect';
import { convertFromRaw } from 'draft-js';
import stateToMarkdown from '../stateToMarkdown';
import fs from 'fs';
import { join } from 'path'; // This separates the test cases in `data/test-cases.txt`.

var SEP = '\n\n>>';
var testCasesRaw = fs.readFileSync(join(__dirname, '..', '..', 'test', 'test-cases.txt'), 'utf8');
var testCases = testCasesRaw.slice(2).trim().split(SEP).map(function (text) {
  var lines = text.split('\n');

  var _lines$shift$split = lines.shift().split('|'),
      _lines$shift$split2 = _slicedToArray(_lines$shift$split, 2),
      description = _lines$shift$split2[0],
      config = _lines$shift$split2[1];

  description = description.trim();
  var options = config ? JSON.parse(config.trim()) : undefined;
  var state = JSON.parse(lines.shift());
  var markdown = lines.join('\n');
  return {
    description: description,
    state: state,
    markdown: markdown,
    options: options
  };
});
describe('stateToMarkdown', function () {
  testCases.forEach(function (testCase) {
    var description = testCase.description,
        state = testCase.state,
        markdown = testCase.markdown,
        options = testCase.options;
    it("should render ".concat(description), function () {
      var contentState = convertFromRaw(state);
      expect(stateToMarkdown(contentState, options)).toBe(markdown + '\n');
    });
  });
});