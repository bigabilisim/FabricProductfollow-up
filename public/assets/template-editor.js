(() => {
  const target = document.getElementById('template-editor');
  const form = document.querySelector('[data-template-form]');
  if (!target || !form || !window.grapesjs) {
    return;
  }

  const initialHtml = target.dataset.templateHtml || '<section><h1>Baslik</h1><p>Icerik</p></section>';
  const initialCss = target.dataset.templateCss || '';
  const initialProject = target.dataset.templateProject || '';
  const type = target.dataset.templateType || 'mail';

  const editor = grapesjs.init({
    container: '#template-editor',
    height: '720px',
    storageManager: false,
    fromElement: false,
    components: initialHtml,
    style: initialCss,
    deviceManager: {
      devices: [
        { name: 'Desktop', width: '' },
        { name: 'Mobil', width: '420px', widthMedia: '480px' }
      ]
    }
  });

  try {
    if (initialProject.trim()) {
      editor.loadProjectData(JSON.parse(initialProject));
    }
  } catch (error) {
    editor.setComponents(initialHtml);
    editor.setStyle(initialCss);
  }

  const blocks = editor.BlockManager;
  blocks.add('section', {
    label: 'Bolum',
    category: 'Temel',
    content: '<section style="padding:24px;"><h2>Baslik</h2><p>Metin alani</p></section>'
  });
  blocks.add('text', {
    label: 'Metin',
    category: 'Temel',
    content: '<p>Metin yazin</p>'
  });
  blocks.add('button', {
    label: 'Buton',
    category: 'Temel',
    content: '<a href="#" style="display:inline-block;padding:10px 14px;background:#0f766e;color:#fff;text-decoration:none;border-radius:6px;">Buton</a>'
  });
  blocks.add('image', {
    label: 'Gorsel',
    category: 'Temel',
    content: '<img src="/assets/pwa-icon.svg" style="max-width:160px;height:auto;">'
  });
  blocks.add('table', {
    label: 'Tablo',
    category: 'Rapor',
    content: '<table style="width:100%;border-collapse:collapse;"><tr><th style="border-bottom:1px solid #dce5e1;text-align:left;padding:8px;">Baslik</th><th style="border-bottom:1px solid #dce5e1;text-align:left;padding:8px;">Deger</th></tr><tr><td style="padding:8px;">{{device_code}}</td><td style="padding:8px;">{{status}}</td></tr></table>'
  });

  if (type === 'mail') {
    blocks.add('maintenance-buttons', {
      label: 'Bakim Butonlari',
      category: 'Mail',
      content: '<p><a href="{{done_url}}" style="display:inline-block;padding:10px 14px;background:#087443;color:#fff;text-decoration:none;border-radius:6px;">Yapildi</a> <a href="{{not_done_url}}" style="display:inline-block;padding:10px 14px;background:#b42318;color:#fff;text-decoration:none;border-radius:6px;">Yapilmadi</a> <a href="{{rescheduled_url}}" style="display:inline-block;padding:10px 14px;background:#b45309;color:#fff;text-decoration:none;border-radius:6px;">Baska zamana planlandi</a></p>'
    });
  }

  form.addEventListener('submit', () => {
    document.getElementById('template-html').value = editor.getHtml();
    document.getElementById('template-css').value = editor.getCss();
    document.getElementById('template-project').value = JSON.stringify(editor.getProjectData());
  });
})();
