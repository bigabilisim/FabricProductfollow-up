(() => {
  const scriptUrl = new URL(document.currentScript ? document.currentScript.src : '/assets/pwa.js', window.location.href);
  const appUrl = (path) => new URL(path, scriptUrl).toString();
  const csrf = document.querySelector('meta[name="csrf-token"]')?.content || '';
  let registrationPromise = null;

  if ('serviceWorker' in navigator) {
    registrationPromise = navigator.serviceWorker.register(appUrl('../service-worker.js'));
  }

  const panel = document.querySelector('[data-web-push-panel]');
  if (!panel) {
    return;
  }

  const status = panel.querySelector('[data-web-push-status]');
  const count = panel.querySelector('[data-web-push-count]');
  const enableButton = panel.querySelector('[data-web-push-enable]');
  const testButton = panel.querySelector('[data-web-push-test]');

  const setStatus = (message, isError = false) => {
    if (!status) {
      return;
    }

    status.textContent = message;
    status.className = 'web-push-status ' + (isError ? 'error' : 'ok');
  };

  const vapidToUint8Array = (value) => {
    const padding = '='.repeat((4 - value.length % 4) % 4);
    const base64 = (value + padding).replace(/-/g, '+').replace(/_/g, '/');
    const raw = window.atob(base64);
    const output = new Uint8Array(raw.length);

    for (let index = 0; index < raw.length; index++) {
      output[index] = raw.charCodeAt(index);
    }

    return output;
  };

  const postJson = async (path, payload) => {
    const response = await fetch(appUrl('..' + path), {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'X-CSRF-Token': csrf
      },
      body: JSON.stringify(payload || {})
    });
    const data = await response.json();

    if (!response.ok || !data.ok) {
      throw new Error(data.message || 'Istek tamamlanamadi.');
    }

    return data;
  };

  const publicKey = async () => {
    const response = await fetch(appUrl('../web-push/public-key'), {
      headers: {
        'Accept': 'application/json'
      }
    });
    const data = await response.json();

    if (!response.ok || !data.ok || !data.publicKey) {
      throw new Error(data.message || 'Web Push anahtari alinamadi.');
    }

    if (count && Number.isFinite(Number(data.subscriptionCount))) {
      count.textContent = data.subscriptionCount;
    }

    return data.publicKey;
  };

  const subscriptionPayload = (subscription) => {
    const payload = subscription.toJSON();
    const encodings = window.PushManager && PushManager.supportedContentEncodings
      ? PushManager.supportedContentEncodings
      : [];

    payload.contentEncoding = encodings.includes('aes128gcm') ? 'aes128gcm' : 'aesgcm';
    return payload;
  };

  const enablePush = async () => {
    if (!registrationPromise || !('PushManager' in window) || !('Notification' in window)) {
      throw new Error('Bu tarayici Web Push desteklemiyor.');
    }

    const permission = await Notification.requestPermission();
    if (permission !== 'granted') {
      throw new Error('Bildirim izni verilmedi.');
    }

    const registration = await registrationPromise;
    const key = await publicKey();
    let subscription = await registration.pushManager.getSubscription();
    if (!subscription) {
      subscription = await registration.pushManager.subscribe({
        userVisibleOnly: true,
        applicationServerKey: vapidToUint8Array(key)
      });
    }

    const data = await postJson('/web-push/subscribe', subscriptionPayload(subscription));
    setStatus(data.message || 'Web Push aktif.');
    await publicKey();
  };

  const sendTest = async () => {
    const data = await postJson('/web-push/test', {});
    setStatus(data.message || 'Test bildirimi gonderildi.');
  };

  enableButton?.addEventListener('click', async () => {
    enableButton.disabled = true;
    setStatus('Bildirim izni hazirlaniyor...');
    try {
      await enablePush();
    } catch (error) {
      setStatus(error.message || 'Web Push acilamadi.', true);
    } finally {
      enableButton.disabled = false;
    }
  });

  testButton?.addEventListener('click', async () => {
    testButton.disabled = true;
    setStatus('Test bildirimi gonderiliyor...');
    try {
      await sendTest();
    } catch (error) {
      setStatus(error.message || 'Test bildirimi gonderilemedi.', true);
    } finally {
      testButton.disabled = false;
    }
  });

  if (Notification.permission === 'granted') {
    setStatus('Bu tarayicida bildirim izni verilmis.');
  }
})();
