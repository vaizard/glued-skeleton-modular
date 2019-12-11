function _objectWithoutProperties(source, excluded) { if (source == null) return {}; var target = _objectWithoutPropertiesLoose(source, excluded); var key, i; if (Object.getOwnPropertySymbols) { var sourceSymbolKeys = Object.getOwnPropertySymbols(source); for (i = 0; i < sourceSymbolKeys.length; i++) { key = sourceSymbolKeys[i]; if (excluded.indexOf(key) >= 0) continue; if (!Object.prototype.propertyIsEnumerable.call(source, key)) continue; target[key] = source[key]; } } return target; }

function _objectWithoutPropertiesLoose(source, excluded) { if (source == null) return {}; var target = {}; var sourceKeys = Object.keys(source); var key, i; for (i = 0; i < sourceKeys.length; i++) { key = sourceKeys[i]; if (excluded.indexOf(key) >= 0) continue; target[key] = source[key]; } return target; }

function ownKeys(object, enumerableOnly) { var keys = Object.keys(object); if (Object.getOwnPropertySymbols) { keys.push.apply(keys, Object.getOwnPropertySymbols(object)); } if (enumerableOnly) keys = keys.filter(function (sym) { return Object.getOwnPropertyDescriptor(object, sym).enumerable; }); return keys; }

function _objectSpread(target) { for (var i = 1; i < arguments.length; i++) { var source = arguments[i] != null ? arguments[i] : {}; if (i % 2) { ownKeys(source, true).forEach(function (key) { _defineProperty(target, key, source[key]); }); } else if (Object.getOwnPropertyDescriptors) { Object.defineProperties(target, Object.getOwnPropertyDescriptors(source)); } else { ownKeys(source).forEach(function (key) { Object.defineProperty(target, key, Object.getOwnPropertyDescriptor(source, key)); }); } } return target; }

function _defineProperty(obj, key, value) { if (key in obj) { Object.defineProperty(obj, key, { value: value, enumerable: true, configurable: true, writable: true }); } else { obj[key] = value; } return obj; }

var _global = global,
    describe = _global.describe,
    it = _global.it;
import expect from 'expect';
import stateFromMarkdown from '../stateFromMarkdown';
import { convertToRaw } from 'draft-js';
describe('stateFromMarkdown', function () {
  it('should create content state', function () {
    var markdown = 'Hello World';
    var contentState = stateFromMarkdown(markdown);
    var rawContentState = convertToRaw(contentState);
    var blocks = removeKeys(rawContentState.blocks);
    expect(blocks).toEqual([{
      text: 'Hello World',
      type: 'unstyled',
      data: {},
      depth: 0,
      inlineStyleRanges: [],
      entityRanges: []
    }]);
  });
  it('should correctly handle code blocks', function () {
    var markdown = "```\nconst a = 'b'\n```";
    var contentState = stateFromMarkdown(markdown);
    var rawContentState = convertToRaw(contentState);
    var blocks = removeKeys(rawContentState.blocks);
    expect(blocks).toEqual([{
      text: "const a = 'b'",
      type: 'code-block',
      data: {},
      depth: 0,
      inlineStyleRanges: [{
        length: 13,
        offset: 0,
        style: 'CODE'
      }],
      entityRanges: []
    }]);
  });
  it('should correctly handle code blocks with languages', function () {
    var markdown = "```javascript\nconst a = 'b'\n```";
    var contentState = stateFromMarkdown(markdown);
    var rawContentState = convertToRaw(contentState);
    var blocks = removeKeys(rawContentState.blocks);
    expect(blocks).toEqual([{
      text: "const a = 'b'",
      type: 'code-block',
      data: {
        language: 'javascript'
      },
      depth: 0,
      inlineStyleRanges: [{
        length: 13,
        offset: 0,
        style: 'CODE'
      }],
      entityRanges: []
    }]);
  });
  it('should correctly handle linebreaks option', function () {
    var markdown = 'Hello\nWorld';
    var contentState = stateFromMarkdown(markdown, {
      parserOptions: {
        breaks: true
      }
    });
    var rawContentState = convertToRaw(contentState);
    var blocks = removeKeys(rawContentState.blocks);
    expect(blocks).toEqual([{
      text: 'Hello\nWorld',
      type: 'unstyled',
      depth: 0,
      inlineStyleRanges: [],
      entityRanges: [],
      data: {}
    }]);
  });
  it('should correctly handle images with alt text', function () {
    var src = 'https://google.com/logo.png';
    var alt = 'The Google Logo';
    var markdown = "![".concat(alt, "](").concat(src, ")");
    var contentState = stateFromMarkdown(markdown, {
      parserOptions: {
        atomicImages: true
      }
    });
    var rawContentState = convertToRaw(contentState);
    var blocks = removeKeys(rawContentState.blocks);
    expect(_objectSpread({}, rawContentState, {
      blocks: blocks
    })).toEqual({
      entityMap: _defineProperty({}, 0, {
        type: 'IMAGE',
        mutability: 'MUTABLE',
        data: {
          src: src,
          alt: alt
        }
      }),
      blocks: [{
        text: ' ',
        type: 'atomic',
        depth: 0,
        inlineStyleRanges: [],
        entityRanges: [{
          offset: 0,
          length: 1,
          key: 0
        }],
        data: {}
      }]
    });
  });
  it('should correctly handle images with complex srcs', function () {
    var src = 'https://spectrum.imgix.net/threads/c678032e-68a4-4e14-956d-abfa444a707d/Captura%20de%20pantalla%202017-08-19%20a%20la(s)%2000.14.09.png.0.29802431313299893';
    var markdown = "![](".concat(src, ")");
    var contentState = stateFromMarkdown(markdown);
    var rawContentState = convertToRaw(contentState);
    var blocks = removeKeys(rawContentState.blocks);
    expect(_objectSpread({}, rawContentState, {
      blocks: blocks
    })).toEqual({
      entityMap: _defineProperty({}, 0, {
        type: 'IMAGE',
        mutability: 'MUTABLE',
        data: {
          src: src
        }
      }),
      blocks: [{
        text: ' ',
        type: 'unstyled',
        depth: 0,
        inlineStyleRanges: [],
        entityRanges: [{
          offset: 0,
          length: 1,
          key: 0
        }],
        data: {}
      }]
    });
  });
  it('should correctly links', function () {
    var _entityMap3;

    var markdown = "[link1](https://google.com) [link2](https://google.com)";
    var contentState = stateFromMarkdown(markdown);
    var rawContentState = convertToRaw(contentState);
    var blocks = removeKeys(rawContentState.blocks);
    expect(_objectSpread({}, rawContentState, {
      blocks: blocks
    })).toEqual({
      entityMap: (_entityMap3 = {}, _defineProperty(_entityMap3, 0, {
        type: 'LINK',
        mutability: 'MUTABLE',
        data: {
          url: 'https://google.com'
        }
      }), _defineProperty(_entityMap3, 1, {
        type: 'LINK',
        mutability: 'MUTABLE',
        data: {
          url: 'https://google.com'
        }
      }), _entityMap3),
      blocks: [{
        text: 'link1 link2',
        type: 'unstyled',
        depth: 0,
        inlineStyleRanges: [],
        entityRanges: [{
          offset: 0,
          length: 5,
          key: 0
        }, {
          offset: 6,
          length: 5,
          key: 1
        }],
        data: {}
      }]
    });
  });
  it('should correctly parse link containing escaped parenthesis', function () {
    var markdown = "[link1](http://msdn.microsoft.com/en-us/library/aa752574%28VS.85%29.aspx)";
    var contentState = stateFromMarkdown(markdown);
    var rawContentState = convertToRaw(contentState);
    var blocks = removeKeys(rawContentState.blocks);
    expect(_objectSpread({}, rawContentState, {
      blocks: blocks
    })).toEqual({
      entityMap: _defineProperty({}, 0, {
        type: 'LINK',
        mutability: 'MUTABLE',
        data: {
          url: 'http://msdn.microsoft.com/en-us/library/aa752574%28VS.85%29.aspx'
        }
      }),
      blocks: [{
        text: 'link1',
        type: 'unstyled',
        depth: 0,
        inlineStyleRanges: [],
        entityRanges: [{
          offset: 0,
          length: 5,
          key: 0
        }],
        data: {}
      }]
    });
  });
});

function removeKeys(blocks) {
  return blocks.map(function (block) {
    // eslint-disable-next-line no-unused-vars
    var key = block.key,
        other = _objectWithoutProperties(block, ["key"]);

    return other;
  });
}