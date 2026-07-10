// 投稿編集エディタ（ドラッグ&ドロップ入稿 + 本文ライブプレビュー）
// - ヒーロー画像: ドロップ/選択 → file input にセットしてクライアント側プレビュー（保存時にアップロード）
// - 本文用画像 : ドロップ/選択 → 即時AJAXアップロード → [image:ID] をカーソル位置へ挿入
// - プレビュー : サーバー側 PostImage::renderBody() と同じ規則（エスケープ→改行→[image:ID]置換）で描画
(function () {
  'use strict';

  var form = document.getElementById('post-form');
  if (form === null) {
    return;
  }

  var postId = form.dataset.postId;
  var uploadUrl = form.dataset.uploadUrl;
  var deleteHeroUrl = form.dataset.deleteHeroUrl;
  var csrfToken = form.querySelector('input[name="csrf_token"]').value;

  // 本文画像の ID => URL マップ（既存分はサーバーから受け取る）
  var imageMap = JSON.parse(form.dataset.imageMap || '{}');

  var textarea = document.getElementById('body');
  var preview = document.getElementById('body-preview');
  var bodyStatus = document.getElementById('upload-status');
  var heroStatus = document.getElementById('hero-status');
  var cancelLink = document.getElementById('cancel-link');

  function setStatus(el, message, isError) {
    el.hidden = message === '';
    el.textContent = message;
    el.classList.toggle('upload-error', Boolean(isError));
  }

  // ---- 未保存の変更の検知（キャンセル時の確認に使用） ----------

  var dirty = false;
  function markDirty() { dirty = true; }

  ['type', 'title'].forEach(function (fieldId) {
    var field = document.getElementById(fieldId);
    if (field !== null) {
      field.addEventListener('input', markDirty);
      field.addEventListener('change', markDirty);
    }
  });

  form.querySelectorAll('input[name="tags[]"], input[name="delete_images[]"]').forEach(function (checkbox) {
    checkbox.addEventListener('change', markDirty);
  });

  if (cancelLink !== null) {
    cancelLink.addEventListener('click', function (event) {
      if (dirty && !window.confirm('変更内容を保存しなくて良いですか？')) {
        event.preventDefault();
      }
    });
  }

  // ---- ライブプレビュー ----------------------------------------

  function escapeHtml(text) {
    return text
      .replace(/&/g, '&amp;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;')
      .replace(/'/g, '&#039;');
  }

  function renderPreview() {
    var html = escapeHtml(textarea.value).replace(/\r?\n/g, '<br>\n');

    html = html.replace(/\[image:(\d+)\]/g, function (match, id) {
      if (!Object.prototype.hasOwnProperty.call(imageMap, id)) {
        return '';
      }
      return '<img src="' + escapeHtml(imageMap[id]) + '" alt="" class="body-image">';
    });

    preview.innerHTML = html;
  }

  textarea.addEventListener('input', function () {
    markDirty();
    renderPreview();
  });
  renderPreview();

  // ---- 共通: ドロップゾーンのイベント配線 ----------------------

  function wireDropzone(zone, onFiles) {
    ['dragenter', 'dragover'].forEach(function (type) {
      zone.addEventListener(type, function (event) {
        event.preventDefault();
        zone.classList.add('dropzone-active');
      });
    });

    ['dragleave', 'drop'].forEach(function (type) {
      zone.addEventListener(type, function (event) {
        event.preventDefault();
        zone.classList.remove('dropzone-active');
      });
    });

    zone.addEventListener('drop', function (event) {
      var files = Array.prototype.filter.call(
        event.dataTransfer.files,
        function (file) { return file.type.indexOf('image/') === 0; }
      );
      if (files.length > 0) {
        onFiles(files);
      }
    });
  }

  // ---- ヒーロー画像: ドロップ → input にセット + プレビュー ----

  var heroZone = document.getElementById('hero-dropzone');
  var heroInput = document.getElementById('hero_image');
  var heroPreview = document.getElementById('hero-preview');
  var heroDeleteBtn = document.getElementById('hero-delete-btn');

  // サーバーに保存済みのヒーロー画像があるかどうか（削除ボタンの挙動の切り替えに使う）
  var heroPersisted = !heroPreview.hidden;

  function showHeroPreview(file) {
    var reader = new FileReader();
    reader.onload = function () {
      heroPreview.src = reader.result;
      heroPreview.hidden = false;
      heroDeleteBtn.hidden = false;
    };
    reader.readAsDataURL(file);
  }

  wireDropzone(heroZone, function (files) {
    var transfer = new DataTransfer();
    transfer.items.add(files[0]); // ヒーローは1枚だけ
    heroInput.files = transfer.files;
    showHeroPreview(files[0]);
    markDirty(); // 保存するまでは未反映の選択のため
  });

  heroZone.addEventListener('click', function () {
    heroInput.click();
  });

  heroInput.addEventListener('change', function () {
    if (heroInput.files.length > 0) {
      showHeroPreview(heroInput.files[0]);
      markDirty();
    }
  });

  // 削除ボタン：保存済み画像はAJAXで即座に削除。未保存の選択のみの場合はローカルでクリアするだけ。
  heroDeleteBtn.addEventListener('click', function () {
    if (!window.confirm('ヒーロー画像を削除しますか？')) {
      return;
    }

    function clearLocalSelection() {
      heroInput.value = '';
      heroPreview.hidden = true;
      heroPreview.src = '';
      heroDeleteBtn.hidden = true;
    }

    if (!heroPersisted) {
      clearLocalSelection();
      return;
    }

    heroDeleteBtn.disabled = true;
    var data = new FormData();
    data.append('csrf_token', csrfToken);
    data.append('post_id', postId);

    fetch(deleteHeroUrl, { method: 'POST', body: data })
      .then(function (response) {
        return response.json().then(function (json) {
          if (!response.ok) {
            throw new Error(json.error || '削除に失敗しました。');
          }
          return json;
        });
      })
      .then(function () {
        heroPersisted = false;
        clearLocalSelection();
        setStatus(heroStatus, '', false);
      })
      .catch(function (error) {
        setStatus(heroStatus, error.message, true);
      })
      .finally(function () {
        heroDeleteBtn.disabled = false;
      });
  });

  // ---- 本文用画像: ドロップ → 即時アップロード → [image:ID] 挿入 ----

  var bodyZone = document.getElementById('body-dropzone');

  function insertAtCursor(text) {
    var start = textarea.selectionStart;
    var end = textarea.selectionEnd;
    var value = textarea.value;
    textarea.value = value.slice(0, start) + text + value.slice(end);
    textarea.selectionStart = textarea.selectionEnd = start + text.length;
    textarea.focus();
  }

  function uploadBodyImage(file) {
    var data = new FormData();
    data.append('csrf_token', csrfToken);
    data.append('post_id', postId);
    data.append('image', file);

    return fetch(uploadUrl, { method: 'POST', body: data })
      .then(function (response) {
        return response.json().then(function (json) {
          if (!response.ok) {
            throw new Error(json.error || 'アップロードに失敗しました。');
          }
          return json;
        });
      });
  }

  function handleBodyFiles(files) {
    setStatus(bodyStatus, 'アップロード中…', false);

    // 複数ファイルは順番に処理して挿入順を安定させる
    var chain = Promise.resolve();
    files.forEach(function (file) {
      chain = chain.then(function () {
        return uploadBodyImage(file).then(function (json) {
          imageMap[String(json.id)] = json.url;
          insertAtCursor(json.placeholder + '\n');
          markDirty(); // 本文への挿入は保存するまで反映されないため
          renderPreview();
        });
      });
    });

    chain
      .then(function () { setStatus(bodyStatus, '', false); })
      .catch(function (error) { setStatus(bodyStatus, error.message, true); });
  }

  wireDropzone(bodyZone, handleBodyFiles);

  // クリックでもファイル選択できるように、非表示のinputを動的に用意
  var bodyPicker = document.createElement('input');
  bodyPicker.type = 'file';
  bodyPicker.accept = 'image/*';
  bodyPicker.multiple = true;
  bodyPicker.hidden = true;
  bodyZone.appendChild(bodyPicker);

  bodyZone.addEventListener('click', function (event) {
    if (event.target !== bodyPicker) {
      bodyPicker.click();
    }
  });

  bodyPicker.addEventListener('change', function () {
    if (bodyPicker.files.length > 0) {
      handleBodyFiles(Array.prototype.slice.call(bodyPicker.files));
      bodyPicker.value = '';
    }
  });
})();
