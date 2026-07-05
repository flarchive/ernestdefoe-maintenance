import app from 'flarum/admin/app';
import { extend } from 'flarum/common/extend';
import Button from 'flarum/common/components/Button';
import LoadingModal from 'flarum/admin/components/LoadingModal';

declare const m: any;
const t = (k: string) => app.translator.trans('ernestdefoe-maintenance.admin.' + k);

/**
 * Adds Run Migrations + Publish Assets to the dashboard StatusWidget tools
 * dropdown, beside core's Clear Cache and System Info. Both call admin-gated
 * endpoints that replicate the CLI commands in-process, so admins on shared
 * hosting never need a terminal after installing or updating an extension.
 */
function runChore(url: string, doneKey: string, reload: boolean) {
  app.modal.show(LoadingModal);

  app
    .request({ method: 'POST', url: app.forum.attribute('apiUrl') + url })
    .then((response: any) => {
      app.modal.close();
      app.alerts.clear();
      app.alerts.show({ type: 'success' }, t(doneKey));
      const log = (response && response.log) || [];
      if (log.length) console.info('[maintenance] ' + url + '\n' + log.join('\n'));
      if (reload) window.location.reload();
    })
    .catch(() => {
      app.modal.close();
      app.alerts.clear();
      app.alerts.show({ type: 'error' }, t('failed'));
    });
}

app.initializers.add('ernestdefoe-maintenance', () => {
  extend('flarum/admin/components/StatusWidget', 'toolsItems', function (items: any) {
    items.add(
      'maintenanceRunMigrations',
      m(Button, { onclick: () => runChore('/maintenance/migrate', 'migrated', true) }, t('migrate_button')),
      8
    );
    items.add(
      'maintenancePublishAssets',
      m(Button, { onclick: () => runChore('/maintenance/assets', 'published', false) }, t('assets_button')),
      6
    );
  });
});
