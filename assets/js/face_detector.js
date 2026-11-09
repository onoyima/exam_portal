/*
 * Offline face detector for the AI proctor.
 *
 * Runs the BlazeFace short-range model that ships in assets/vendor/blazeface/model
 * with TensorFlow.js, so nothing is fetched from the internet during an exam.
 *
 *   const det = await cbtFaceDetector.load('assets/vendor/blazeface/model/blazeface-front.json');
 *   const faces = await det.detect(videoOrCanvas);   // [{score, box:[x1,y1,x2,y2]}] in 0..1 units
 */
(function () {
  'use strict';

  var SIZE = 128;

  // 16x16 grid with 2 anchors per cell (512) followed by 8x8 with 6 (384).
  function buildAnchors(grid, perCell) {
    var out = [];
    for (var y = 0; y < grid; y++) {
      for (var x = 0; x < grid; x++) {
        for (var k = 0; k < perCell; k++) out.push([(x + 0.5) / grid, (y + 0.5) / grid]);
      }
    }
    return out;
  }
  var ANCHORS = { 512: buildAnchors(16, 2), 384: buildAnchors(8, 6) };

  function sigmoid(v) {
    v = Math.max(-80, Math.min(80, v));
    return 1 / (1 + Math.exp(-v));
  }

  async function load(url) {
    if (typeof tf === 'undefined') throw new Error('TensorFlow.js is not loaded');
    var model = await tf.loadGraphModel(url);

    // Work out which outputs carry scores and which carry boxes for each anchor set.
    var probe = tf.zeros([1, SIZE, SIZE, 3]);
    var outs = model.execute(probe);
    outs = Array.isArray(outs) ? outs : [outs];
    var layout = outs.map(function (t) { return t.shape; });
    tf.dispose(outs);
    probe.dispose();

    function pick(list, anchors, depth) {
      for (var i = 0; i < layout.length; i++) {
        if (layout[i][1] === anchors && layout[i][2] === depth) return list[i];
      }
      return null;
    }

    async function detect(source, minScore, maxFaces) {
      minScore = minScore || 0.75;
      maxFaces = maxFaces || 10;
      // Pad to a square first: stretching a 4:3 webcam frame squashes faces
      // and lowers the detector's confidence.
      var sx = 1, sy = 1;
      var input = tf.tidy(function () {
        var px = tf.browser.fromPixels(source);
        var h = px.shape[0], w = px.shape[1], side = Math.max(h, w);
        sx = side / w;
        sy = side / h;
        var sq = tf.pad(px, [[0, side - h], [0, side - w], [0, 0]]);
        return tf.image.resizeBilinear(sq, [SIZE, SIZE]).toFloat().div(127.5).sub(1).expandDims(0);
      });
      var res = model.execute(input);
      res = Array.isArray(res) ? res : [res];
      input.dispose();

      var boxes = [];
      var scores = [];
      for (var n of [512, 384]) {
        var s = pick(res, n, 1), b = pick(res, n, 16);
        if (!s || !b) continue;
        var sv = await s.data(), bv = await b.data(), anchors = ANCHORS[n];
        for (var i = 0; i < n; i++) {
          var sc = sigmoid(sv[i]);
          if (sc < minScore) continue;
          var o = i * 16;
          var cx = anchors[i][0] + bv[o] / SIZE;
          var cy = anchors[i][1] + bv[o + 1] / SIZE;
          var w = bv[o + 2] / SIZE, h = bv[o + 3] / SIZE;
          boxes.push([cy - h / 2, cx - w / 2, cy + h / 2, cx + w / 2]);
          scores.push(sc);
        }
      }
      tf.dispose(res);
      if (!boxes.length) return [];

      var bt = tf.tensor2d(boxes), st = tf.tensor1d(scores);
      var keep = await tf.image.nonMaxSuppressionAsync(bt, st, maxFaces, 0.3, minScore);
      var idx = await keep.data();
      tf.dispose([bt, st, keep]);
      return Array.from(idx).map(function (i) {
        var b = boxes[i];
        return { score: scores[i], box: [b[1] * sx, b[0] * sy, b[3] * sx, b[2] * sy] };
      });
    }

    return { detect: detect };
  }

  window.cbtFaceDetector = { load: load };
})();
