document.addEventListener('DOMContentLoaded', () => {
  const editor = document.getElementById('timellow-markdown-editor');
  if (!editor || typeof timellowMarkdown === 'undefined') return;

  const textarea = editor.querySelector('#content');
  const preview = editor.querySelector('.timellow-md-preview');
  const status = editor.querySelector('.timellow-md-status');
  const modeInput = editor.querySelector('[name="timellow_md_mode"]');
  const convertButton = document.getElementById('timellow-convert-markdown');
  let previewTimer;
  let previewRequest;
  let conversionPending = false;

  if (convertButton) {
    const publishingAction = document.getElementById('publishing-action');
    publishingAction?.insertBefore(convertButton, publishingAction.querySelector('.spinner'));
    convertButton.hidden = modeInput.value !== 'html';
  }

  function setView(view) {
    editor.dataset.view = view;
    editor.querySelectorAll('[data-view]').forEach((button) => {
      button.setAttribute('aria-pressed', String(button.dataset.view === view));
    });
    if (view !== 'edit') renderPreview();
  }

  function setFormat(format, confirmChange = true) {
    if (format === modeInput.value) return;
    if (confirmChange && textarea.value.trim() && !window.confirm('切换格式不会自动转换现有正文，继续吗？')) return;
    modeInput.value = format;
    editor.dataset.format = format;
    if (format !== 'markdown') conversionPending = false;
    if (convertButton) convertButton.hidden = format !== 'html';
    editor.querySelectorAll('[data-format]').forEach((button) => {
      button.setAttribute('aria-pressed', String(button.dataset.format === format));
    });
    renderPreview();
  }

  function convertHtmlToMarkdown() {
    if (modeInput.value !== 'html' || !textarea.value.trim()) return;
    if (typeof window.TurndownService !== 'function' || !window.turndownPluginGfm?.gfm) {
      status.textContent = '转换器未加载，请刷新页面重试。';
      return;
    }

    try {
      const converter = new window.TurndownService({ headingStyle: 'atx', codeBlockStyle: 'fenced', bulletListMarker: '-' });
      converter.use(window.turndownPluginGfm.gfm);
      const markdown = converter.turndown(textarea.value);
      if (!markdown.trim()) {
        status.textContent = '未找到可转换的正文。';
        return;
      }
      if (!window.confirm('将当前 HTML 正文转换为 Markdown？转换后请检查预览，再点击发布或更新保存。')) return;

      conversionPending = true;
      textarea.value = markdown;
      setFormat('markdown', false);
      textarea.dispatchEvent(new Event('input', { bubbles: true }));
      status.textContent = '已转换为 Markdown，尚未保存。';
      textarea.focus();
    } catch (error) {
      status.textContent = '转换失败，原正文未改动。';
    }
  }

  async function renderPreview() {
    if (editor.dataset.view === 'edit') return;
    if (previewRequest) previewRequest.abort();
    previewRequest = new AbortController();
    status.textContent = '正在生成预览…';
    const body = new FormData();
    body.append('action', 'timellow_md_preview');
    body.append('nonce', timellowMarkdown.nonce);
    body.append('post_id', timellowMarkdown.postId);
    body.append('mode', modeInput.value);
    body.append('source', textarea.value);

    try {
      const response = await fetch(timellowMarkdown.ajaxUrl, {
        method: 'POST',
        credentials: 'same-origin',
        body,
        signal: previewRequest.signal,
      });
      const result = await response.json();
      if (!response.ok || !result.success) throw new Error(result.data?.message || '预览失败');
      preview.innerHTML = result.data.html;
      status.textContent = conversionPending ? '已转换为 Markdown，尚未保存。' : '';
    } catch (error) {
      if (error.name !== 'AbortError') status.textContent = error.message || '预览失败';
    }
  }

  function schedulePreview() {
    window.clearTimeout(previewTimer);
    previewTimer = window.setTimeout(renderPreview, 260);
  }

  function insertText(text, selectStart, selectEnd) {
    const start = textarea.selectionStart;
    const end = textarea.selectionEnd;
    textarea.setRangeText(text, start, end, 'end');
    textarea.focus();
    textarea.setSelectionRange(start + selectStart, start + selectEnd);
    textarea.dispatchEvent(new Event('input', { bubbles: true }));
  }

  function wrap(prefix, suffix, placeholder) {
    const selection = textarea.value.slice(textarea.selectionStart, textarea.selectionEnd) || placeholder;
    insertText(prefix + selection + suffix, prefix.length, prefix.length + selection.length);
  }

  function prefixLines(type) {
    const start = textarea.selectionStart;
    const end = textarea.selectionEnd;
    const lineStart = textarea.value.lastIndexOf('\n', start - 1) + 1;
    const nextLine = textarea.value.indexOf('\n', end);
    const lineEnd = nextLine < 0 ? textarea.value.length : nextLine;
    const original = textarea.value.slice(lineStart, lineEnd);
    const lines = original.split('\n');
    const replacement = lines.map((line, index) => {
      const prefix = type === 'ol' ? `${index + 1}. ` : type === 'ul' ? '- ' : type === 'quote' ? '> ' : '## ';
      return prefix + line;
    }).join('\n');
    textarea.setRangeText(replacement, lineStart, lineEnd, 'select');
    textarea.focus();
    textarea.dispatchEvent(new Event('input', { bubbles: true }));
  }

  function insertImage() {
    if (!window.wp?.media) return;
    const picker = window.wp.media({ title: '选择图片', button: { text: '插入图片' }, multiple: false, library: { type: 'image' } });
    picker.on('select', () => {
      const image = picker.state().get('selection').first().toJSON();
      const alt = (image.alt || image.title || '图片').replace(/[\[\]]/g, '');
      const markdown = `![${alt}](${image.url})`;
      insertText(markdown, markdown.length, markdown.length);
    });
    picker.open();
  }

  function runAction(action) {
    if (action === 'fullscreen') {
      const active = editor.classList.toggle('is-fullscreen');
      document.body.classList.toggle('timellow-md-fullscreen-open', active);
      editor.querySelector('[data-action="fullscreen"]').setAttribute('aria-pressed', String(active));
      return;
    }
    if (modeInput.value !== 'markdown') return;
    if (action === 'bold') wrap('**', '**', '加粗文字');
    if (action === 'italic') wrap('*', '*', '斜体文字');
    if (action === 'code') {
      const selection = textarea.value.slice(textarea.selectionStart, textarea.selectionEnd);
      if (selection.includes('\n')) wrap('```\n', '\n```', '代码');
      else wrap('`', '`', '代码');
    }
    if (['heading', 'quote', 'ul', 'ol'].includes(action)) prefixLines(action);
    if (action === 'link') {
      const url = window.prompt('链接地址', 'https://');
      if (url?.trim()) {
        const label = textarea.value.slice(textarea.selectionStart, textarea.selectionEnd) || '链接文字';
        insertText(`[${label}](${url.trim()})`, 1, label.length + 1);
      }
    }
    if (action === 'image') insertImage();
  }

  editor.querySelectorAll('[data-view]').forEach((button) => button.addEventListener('click', () => setView(button.dataset.view)));
  editor.querySelectorAll('[data-format]').forEach((button) => button.addEventListener('click', () => setFormat(button.dataset.format)));
  editor.querySelectorAll('[data-action]').forEach((button) => button.addEventListener('click', () => runAction(button.dataset.action)));
  convertButton?.addEventListener('click', convertHtmlToMarkdown);
  textarea.addEventListener('input', schedulePreview);
  textarea.addEventListener('keydown', (event) => {
    if (event.key === 'Tab') {
      event.preventDefault();
      insertText('  ', 2, 2);
    }
    if ((event.metaKey || event.ctrlKey) && modeInput.value === 'markdown') {
      if (event.key.toLowerCase() === 'b') { event.preventDefault(); runAction('bold'); }
      if (event.key.toLowerCase() === 'i') { event.preventDefault(); runAction('italic'); }
    }
  });
  document.addEventListener('keydown', (event) => {
    if (event.key === 'Escape' && editor.classList.contains('is-fullscreen')) runAction('fullscreen');
  });

  if (window.matchMedia('(max-width: 900px)').matches) setView('edit');
  else renderPreview();
});
