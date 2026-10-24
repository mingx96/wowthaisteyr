/** Staff schedule editor. Only the hours API owns schedule writes. */
document.addEventListener('DOMContentLoaded', () => {
  const root = document.getElementById('wto-view-hours');
  if (!root || !window.wtoPortalConfig) return;
  const cfg = window.wtoPortalConfig;
  const $ = id => document.getElementById('wto-hours-' + id);
  const esc = value => String(value ?? '').replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
  const days = ['Montag','Dienstag','Mittwoch','Donnerstag','Freitag','Samstag','Sonntag'];
  const today = new Intl.DateTimeFormat('sv-SE', {timeZone:'Europe/Vienna',year:'numeric',month:'2-digit',day:'2-digit'}).format(new Date());
  let schedule = null, loaded = false, loading = false, saving = false, editable = false;
  let kind = 'weekly', token = null, conflicts = [], generation = 0, stale = false;
  const validDate = value => /^\d{4}-\d{2}-\d{2}$/.test(value) && !Number.isNaN(Date.parse(value + 'T12:00:00Z')) && new Date(value + 'T12:00:00Z').toISOString().slice(0,10) === value;
  function exceptionDateIssue(focus = false) {
    const start = $('start'), end = $('end'), error = $('date-error');
    start.removeAttribute('aria-invalid'); end.removeAttribute('aria-invalid');
    let field = null, text = '';
    if (!validDate(start.value)) { field = start; text = 'Bitte ein gültiges Startdatum wählen.'; }
    else if (!validDate(end.value)) { field = end; text = 'Bitte ein gültiges Enddatum wählen.'; }
    else if (end.value < start.value) { field = end; text = 'Das Enddatum darf nicht vor dem Startdatum liegen.'; }
    else if ((Date.parse(end.value) - Date.parse(start.value)) / 86400000 > 366) { field = end; text = 'Ein Zeitraum darf maximal 367 Tage umfassen.'; }
    error.textContent = text; error.hidden = !text;
    if (field) {
      field.setAttribute('aria-invalid', 'true');
      if (focus) {
        tab('exceptions');
        field.focus({preventScroll:false});
      }
    }
    return text;
  }
  function syncExceptionStart() {
    const start = $('start'), end = $('end');
    end.min = validDate(start.value) ? start.value : '';
    if (validDate(start.value)) {
      if (!validDate(end.value) || end.value < start.value) end.value = start.value;
      $('preview-date').value = start.value;
    }
    exceptionDateIssue();
  }
  function message(text, error = false, focus = false) {
    $('message').textContent = text;
    $('message').classList.toggle('is-error', error);
    $('message').setAttribute('role', error ? 'alert' : 'status');
    if (focus) $('message').focus({preventScroll:false});
  }
  async function request(path, method = 'GET', body) {
    const response = await fetch(cfg.hours_api + path, {method, cache:'no-store', signal:AbortSignal.timeout(20000), headers:{'X-WP-Nonce':cfg.nonce,'Content-Type':'application/json'}, ...(body ? {body:JSON.stringify(body)} : {})});
    const data = await response.json();
    if (!response.ok) { const error = new Error(data.message || 'Öffnungszeiten konnten nicht geladen werden.'); error.status = response.status; throw error; }
    return data;
  }
  function controls() {
    $('lunch-fields').disabled = !editable || saving;
    $('weekly-fields').disabled = !editable || saving;
    $('exception-fields').disabled = !editable || saving;
    root.querySelectorAll('[data-day]').forEach(row => {
      const closed = row.querySelector('[data-closed]').checked;
      row.querySelectorAll('[data-ranges] input, [data-remove], [data-add]').forEach(el => { el.disabled = !editable || saving || closed; });
    });
    $('save').hidden = !editable || !token;
    $('save').disabled = saving || loading || !token || (conflicts.length > 0 && !$('ack').checked);
    $('check').disabled = !editable || !loaded || loading || saving || stale;
    $('reload').disabled = loading || saving;
    $('published').disabled = saving;
    $('ack').disabled = saving;
    $('save').textContent = saving ? 'Wird gespeichert …' : 'Änderung speichern';
  }
  function invalidate() {
    generation++; token = null; conflicts = []; $('ack').checked = false;
    $('ack-label').hidden = true; $('conflicts').replaceChildren();
    $('preview-source').textContent = 'Entwurf geändert · Vorschau erneut laden.';
    $('result').replaceChildren(); controls();
  }
  function tab(name, focus = false) {
    if (name !== 'preview' && kind !== name) { kind = name; invalidate(); }
    root.querySelectorAll('[data-hours-tab]').forEach(button => {
      const selected = button.dataset.hoursTab === name;
      button.setAttribute('aria-selected', String(selected)); button.tabIndex = selected ? 0 : -1;
      $(button.dataset.hoursTab).hidden = !selected;
      if (selected && focus) button.focus();
    });
  }
  root.querySelectorAll('[data-hours-tab]').forEach(button => {
    button.addEventListener('click', () => tab(button.dataset.hoursTab));
    button.addEventListener('keydown', event => {
      const tabs = [...root.querySelectorAll('[data-hours-tab]')]; let next;
      if (event.key === 'ArrowRight') next = (tabs.indexOf(button) + 1) % tabs.length;
      if (event.key === 'ArrowLeft') next = (tabs.indexOf(button) + tabs.length - 1) % tabs.length;
      if (event.key === 'Home') next = 0;
      if (event.key === 'End') next = tabs.length - 1;
      if (next !== undefined) { event.preventDefault(); tab(tabs[next].dataset.hoursTab, true); }
    });
  });
  function rangeHTML(range, label) {
    return `<div class="wto-hours-range"><label>Von<span class="wto-sr-only"> ${esc(label)}</span><input type="time" data-time="start" value="${esc(range[0])}" required></label><label>Bis<span class="wto-sr-only"> ${esc(label)}</span><input type="time" data-time="end" value="${esc(range[1])}" required></label><button type="button" data-remove class="wto-btn btn-secondary" aria-label="Zeitfenster ${esc(label)} entfernen">Entfernen</button></div>`;
  }
  function existing() {
    $('existing').replaceChildren();
    const entries = Object.entries(schedule.exceptions || {}).sort(([a],[b]) => a.localeCompare(b));
    entries.forEach(([date, value]) => {
      const li = document.createElement('li'), button = document.createElement('button');
      button.type = 'button'; button.className = 'wto-btn btn-secondary';
      button.textContent = date + ' · ' + (value.closed ? 'Geschlossen' : (value.ranges || []).map(r => r.join('–')).join(', ')) + (value.reason ? ' · ' + value.reason : '');
      button.addEventListener('click', () => {
        if (saving) return;
        $('start').value = $('end').value = date; $('mode').value = value.closed ? 'closed' : 'hours'; $('reason').value = value.reason || '';
        syncExceptionStart();
        $('custom-ranges').innerHTML = (value.ranges || [['','']]).map(r => rangeHTML(r, 'Sondertag')).join('');
        $('exception-ranges').hidden = $('mode').value !== 'hours'; tab('exceptions'); invalidate();
        message('Gespeicherten Sondertag übernommen. Änderungen erst prüfen und speichern.');
      });
      li.append(button); $('existing').append(li);
    });
    if (!entries.length) $('existing').textContent = 'Keine Sondertage gespeichert.';
  }
  async function load() {
    if (loading || saving) return;
    loading = true; invalidate(); controls(); message('Veröffentlichter Stand wird geladen …');
    try {
      const data = await request('schedule');
      if (!data.weekly || !data.revision || typeof data.can_edit !== 'boolean') throw new Error('Ungültiger Öffnungszeiten-Stand.');
      schedule = data; editable = data.can_edit; stale = false;
      if (!loaded) {
        $('days').innerHTML = days.map((day, i) => {
          const ranges = data.weekly[String(i+1)] || [];
          return `<div class="wto-hours-day" data-day="${i+1}"><div class="wto-hours-day-title"><strong>${day}</strong><label><input type="checkbox" data-closed ${ranges.length ? '' : 'checked'}> Geschlossen</label></div><div data-ranges>${(ranges.length ? ranges : [['','']]).map(r => rangeHTML(r, day)).join('')}</div><button type="button" data-add class="wto-btn btn-secondary">Zeitfenster hinzufügen<span class="wto-sr-only"> ${day}</span></button></div>`;
        }).join('');
        const lunch = data.lunch || {days:[],range:['','']};
        $('lunch-days').innerHTML = days.map((day,i)=>`<label><input type="checkbox" value="${i+1}" ${lunch.days.includes(i+1) ? 'checked' : ''}> ${day}</label>`).join('');
        $('lunch-start').value = lunch.range[0]; $('lunch-end').value = lunch.range[1];
        $('start').value = $('end').value = $('preview-date').value = today;
        syncExceptionStart();
        $('custom-ranges').innerHTML = rangeHTML(['',''], 'Sondertag');
        const query = new URLSearchParams(location.search), date = query.get('hours_date');
        if (validDate(date) && ['closed','remove'].includes(query.get('hours_mode'))) {
          $('start').value = $('end').value = date; $('mode').value = query.get('hours_mode'); $('reason').value = (query.get('hours_reason') || '').slice(0,500); tab('exceptions');
          syncExceptionStart();
        }
      }
      const retained = loaded; loaded = true; existing(); invalidate();
      $('permission').textContent = editable ? 'Änderungen werden erst nach Prüfung gespeichert.' : 'Nur Lesezugriff. Änderungen sind für Administratoren und Shop-Manager freigegeben.';
      message(retained ? 'Aktueller Stand geladen. Ihr Entwurf bleibt erhalten; bitte erneut prüfen.' : 'Veröffentlichter Stand geladen.');
    } catch (error) { editable = false; invalidate(); message(error.message + ' „Stand neu laden“ erneut versuchen. Ihr Entwurf bleibt erhalten.', true); }
    finally { loading = false; controls(); }
  }
  function ranges(container) {
    const rows = [...container.querySelectorAll('.wto-hours-range')];
    const values = rows.map(row => [...row.querySelectorAll('input')].map(input => input.value));
    if (!values.length || values.some(([a,b]) => !/^\d{2}:\d{2}$/.test(a) || !/^\d{2}:\d{2}$/.test(b) || a >= b)) throw new Error('Bitte gültige Zeitfenster mit Von vor Bis eingeben.');
    const ordered = [...values].sort(([a],[b]) => a.localeCompare(b));
    if (ordered.some((r,i) => i && ordered[i-1][1] > r[0])) throw new Error('Zeitfenster dürfen sich nicht überschneiden.');
    return values;
  }
  function draft() {
    const body = {kind, revision:schedule.revision, preview_date:$('preview-date').value};
    if (!validDate(body.preview_date)) throw new Error('Bitte ein gültiges Vorschaudatum wählen.');
    if (kind === 'lunch') {
      const start = $('lunch-start').value, end = $('lunch-end').value;
      if (!start || !end || start >= end) throw new Error('Bitte eine gültige Mittagszeit mit Von vor Bis eingeben.');
      body.lunch = {days:[...$('lunch-days').querySelectorAll('input:checked')].map(el=>Number(el.value)),range:[start,end]};
    } else if (kind === 'weekly') {
      body.weekly = {};
      root.querySelectorAll('[data-day]').forEach(row => { body.weekly[row.dataset.day] = row.querySelector('[data-closed]').checked ? [] : ranges(row.querySelector('[data-ranges]')); });
    } else {
      const start = $('start').value, end = $('end').value;
      const dateIssue = exceptionDateIssue(true);
      if (dateIssue) { const error = new Error(dateIssue); error.validation = true; throw error; }
      body.exception = {start_date:start, end_date:end, mode:$('mode').value, reason:$('reason').value};
      if (body.exception.mode === 'hours') body.exception.ranges = ranges($('custom-ranges'));
    }
    return body;
  }
  function result(data, source) {
    $('preview-source').textContent = source + ' · ' + data.date + ' · Europe/Vienna';
    $('result').innerHTML = `<dl><dt>Küche</dt><dd>${data.ranges?.length ? data.ranges.map(r => esc(r.join('–'))).join(', ') : 'Geschlossen'}</dd><dt>Reservierungen</dt><dd>${esc((data.reservation_slots || []).join(', ') || 'Keine Zeiten')}</dd><dt>Abholung (allgemein)</dt><dd>${esc((data.pickup_slots || []).join(', ') || 'Keine Zeiten')}</dd><dt>Mittagsmenü</dt><dd>${esc(data.mittags_note || '')}</dd><dt>Abholung Mittagsmenü</dt><dd>${esc((data.lunch_slots || []).join(', ') || 'Keine Zeiten')}</dd></dl>`;
  }
  $('check').addEventListener('click', async () => {
    if (!editable || saving || stale) return;
    invalidate(); const current = generation;
    try {
      const body = draft(); message('Entwurf und betroffene Vorgänge werden geprüft …');
      const data = await request('schedule/preview','POST',body);
      if (current !== generation) return;
      if (!data.confirmation_token || !Array.isArray(data.conflicts)) throw new Error('Prüfung unvollständig. Bitte erneut prüfen.');
      token = data.confirmation_token; conflicts = data.conflicts;
      result(data.preview, kind === 'lunch' ? 'Entwurf Mittagsmenü' : (kind === 'weekly' ? 'Entwurf Wochenplan' : 'Entwurf Sondertage'));
      $('conflicts').innerHTML = conflicts.length ? `<h2>${kind === 'lunch' ? 'Aktive Bestellungen prüfen' : 'Betroffene Vorgänge'} (${conflicts.length})</h2><p>Unbekannte Zeiten manuell prüfen. Es wird kein Vorgang storniert.</p><table class="wto-hours-conflict-table"><thead><tr><th scope="col">Vorgang</th><th scope="col">Datum / Zeit</th><th scope="col">Status</th></tr></thead><tbody>${conflicts.map(c => `<tr><td>${c.kind === 'order' ? 'Bestellung' : 'Reservierung'} #${esc(c.id)}</td><td>${esc(c.date || 'Datum unbekannt')} · ${esc(c.time || 'Zeit unbekannt')}</td><td>${esc(c.status)}${c.unknown ? ' · Manuell prüfen' : ''}</td></tr>`).join('')}</tbody></table>` : '<p>Keine betroffenen Vorgänge gefunden.</p>';
      $('ack-label').hidden = !conflicts.length; tab('preview'); controls(); message('Entwurf geprüft. Vorschau gilt 10 Minuten; vor dem Speichern Angaben prüfen.');
    } catch (error) {
      if (current !== generation) return;
      if (error.validation) { invalidate(); message(error.message, true); }
      else failure(error);
    }
  });
  function failure(error) {
    invalidate();
    if (error.status === 409) { stale = true; controls(); message('Stand oder Prüfung ist veraltet. „Stand neu laden“, dann den erhaltenen Entwurf erneut prüfen.',true,true); }
    else message(error.message + ' Ihr Entwurf bleibt erhalten.',true,true);
  }
  $('save').addEventListener('click', async () => {
    if (saving || !editable || !token || (conflicts.length && !$('ack').checked)) return;
    let body;
    try { body = {...draft(), confirmation_token:token, ...($('ack').checked ? {acknowledge_conflicts:true} : {})}; }
    catch (error) { failure(error); return; }
    saving = true; controls(); message('Änderung wird gespeichert …');
    try {
      schedule = await request('schedule/' + kind,'PUT',body); editable = schedule.can_edit === true; existing(); invalidate();
      message('Öffnungszeiten gespeichert. Status und verfügbare Zeiten werden aktualisiert.');
      document.dispatchEvent(new CustomEvent('wowthai:hours-saved',{detail:{revision:schedule.revision}}));
    } catch (error) { failure(error); }
    finally { saving = false; controls(); }
  });
  $('published').addEventListener('click', async () => {
    invalidate(); const current = generation;
    if (!validDate($('preview-date').value)) { message('Bitte ein gültiges Vorschaudatum wählen.',true); return; }
    message('Veröffentlichte Zeiten werden geladen …');
    try { const data = await request('availability?date=' + encodeURIComponent($('preview-date').value)); if (current !== generation) return; result(data,'Veröffentlichter Stand'); message('Veröffentlichte Zeiten geladen.'); }
    catch (error) { if (current === generation) message(error.message,true); }
  });
  $('reload').addEventListener('click',load);
  $('ack').addEventListener('change',controls);
  root.addEventListener('input', event => {
    if (event.target.id === 'wto-hours-start') syncExceptionStart();
    if (event.target.id === 'wto-hours-end') exceptionDateIssue();
    if (event.target.matches('input:not(#wto-hours-ack), select')) invalidate();
  });
  root.addEventListener('focusout', event => {
    if (event.target.matches('#wto-hours-start, #wto-hours-end')) exceptionDateIssue();
  });
  root.addEventListener('change', event => {
    if (event.target.matches('[data-closed], #wto-hours-mode')) { $('exception-ranges').hidden = $('mode').value !== 'hours'; invalidate(); }
  });
  root.addEventListener('click', event => {
    const add = event.target.closest('[data-add], #wto-hours-add-custom'), remove = event.target.closest('[data-remove]');
    if ((!add && !remove) || !editable || saving) return;
    if (add) {
      const row = add.closest('[data-day]'), container = row ? row.querySelector('[data-ranges]') : $('custom-ranges');
      container.insertAdjacentHTML('beforeend',rangeHTML(['',''], row ? days[Number(row.dataset.day)-1] : 'Sondertag'));
      container.lastElementChild.querySelector('input').focus();
    } else {
      const range = remove.closest('.wto-hours-range'), container = range.parentElement;
      range.remove(); (container.querySelector('input') || container.parentElement.querySelector('[data-add], #wto-hours-add-custom'))?.focus();
    }
    invalidate();
  });
  document.addEventListener('wowthai:portal-view', event => { if (event.detail.view === 'hours' && !loaded) load(); });
  if (root.classList.contains('active')) load();
});
