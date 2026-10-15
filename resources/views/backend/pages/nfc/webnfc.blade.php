{{--
   Web NFC helper (Chrome on Android, HTTPS or localhost). window.BicsNfc:
   - supported: true when this browser can read / write NFC tags
   - write(url, {lock}) → Promise<{uid}>: waits for a tag, reads its serial (UID), writes the URL as an NDEF URL record,
     optionally makes the tag read-only (irreversible)
   - read() → Promise<{uid, url}>: waits for a tag and returns its serial and the first URL record
   - cancel(): stops waiting
   Other phones / computers: use an external encoder (e.g. NXP TagWriter app or a USB encoder) with the card's link.
--}}
@once
@push('scripts')
<script>
window.BicsNfc = (function () {
  'use strict';
  var supported = 'NDEFReader' in window && window.isSecureContext;
  var ctrl = null;
  function cancel() { if (ctrl) { try { ctrl.abort(); } catch (e) {} ctrl = null; } }
  function urlOf(message) {
    for (var i = 0; i < message.records.length; i++) {
      var r = message.records[i];
      if ((r.recordType === 'url' || r.recordType === 'absolute-url') && r.data) return new TextDecoder().decode(r.data);
    }
    return null;
  }
  function waitForTag() {
    cancel();
    ctrl = new AbortController();
    var reader = new NDEFReader();
    return new Promise(function (resolve, reject) {
      reader.onreadingerror = function () { reject(new Error('Could not read the card. Hold it still against the back of the phone and try again.')); };
      reader.onreading = function (e) { resolve({ reader: reader, uid: e.serialNumber || null, url: urlOf(e.message) }); };
      reader.scan({ signal: ctrl.signal }).catch(reject);
    });
  }
  function explain(err) {
    var m = (err && (err.message || err.name)) || 'Unknown error';
    if (err && err.name === 'NotAllowedError') return 'NFC permission was refused. Allow NFC for this site in Chrome settings.';
    if (err && err.name === 'NotSupportedError') return 'NFC is turned off or not available on this phone. Turn on NFC in the phone settings.';
    if (err && err.name === 'AbortError') return 'Cancelled.';
    if (err && err.name === 'NetworkError') return 'The card moved away while writing. Hold it still and try again.';
    return m;
  }
  return {
    supported: supported,
    cancel: cancel,
    explain: explain,
    write: function (url, opts) {
      opts = opts || {};
      return waitForTag().then(function (tag) {
        return tag.reader.write({ records: [{ recordType: 'url', data: url }] }, { overwrite: true, signal: ctrl ? ctrl.signal : undefined })
          .then(function () { return opts.lock && tag.reader.makeReadOnly ? tag.reader.makeReadOnly() : null; })
          .then(function () { cancel(); return { uid: tag.uid }; });
      }).catch(function (e) { cancel(); throw e; });
    },
    read: function () {
      return waitForTag().then(function (tag) { cancel(); return { uid: tag.uid, url: tag.url }; }).catch(function (e) { cancel(); throw e; });
    }
  };
})();
</script>
@endpush
@endonce
