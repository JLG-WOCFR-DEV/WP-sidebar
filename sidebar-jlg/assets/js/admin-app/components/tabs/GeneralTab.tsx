import { PanelBody, TextControl, ToggleControl } from '@wordpress/components';

import { useOptionsStore } from '../../store/optionsStore';

const GeneralTab = (): JSX.Element => {
  const options = useOptionsStore((state) => state.options);
  const setOption = useOptionsStore((state) => state.setOption);

  const appName = (options.app_name as string) ?? '';
  const toggleOpenLabel = (options.toggle_open_label as string) ?? '';
  const toggleCloseLabel = (options.toggle_close_label as string) ?? '';
  const navLabel = (options.nav_aria_label as string) ?? '';

  return (
    <div className="sidebar-jlg-tab sidebar-jlg-tab--general">
      <PanelBody title="Accessibilité & activation" initialOpen>
        <ToggleControl
          label="Activer la sidebar sur le site"
          checked={Boolean(options.enable_sidebar)}
          onChange={(value) => setOption('enable_sidebar', value)}
        />
        <ToggleControl
          label="Collecter les métriques (clics, conversions)"
          checked={Boolean(options.enable_analytics)}
          onChange={(value) => setOption('enable_analytics', value)}
          help="Active l'envoi des événements analytics via l'API du plugin."
        />
        <TextControl
          label="Libellé d'ouverture"
          value={toggleOpenLabel}
          onChange={(value) => setOption('toggle_open_label', value)}
          help="Texte vocalisé pour l'action d'ouverture de la sidebar."
        />
        <TextControl
          label="Libellé de fermeture"
          value={toggleCloseLabel}
          onChange={(value) => setOption('toggle_close_label', value)}
        />
        <TextControl
          label="Intitulé ARIA du menu"
          value={navLabel}
          onChange={(value) => setOption('nav_aria_label', value)}
        />
      </PanelBody>
      <PanelBody title="Identité" initialOpen>
        <TextControl
          label="Nom affiché"
          value={appName}
          onChange={(value) => setOption('app_name', value)}
        />
        <TextControl
          label="Position (gauche/droite)"
          value={(options.sidebar_position as string) ?? 'left'}
          onChange={(value) => setOption('sidebar_position', value || 'left')}
          help="Utilisez left ou right selon la position souhaitée."
        />
      </PanelBody>
    </div>
  );
};

export default GeneralTab;
