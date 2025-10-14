import { Panel, TabPanel, Button } from '@wordpress/components';
import { useMemo } from '@wordpress/element';
import { Fragment } from 'react';

import OnboardingModal from './components/OnboardingModal';
import PreviewCanvas from './components/PreviewCanvas';
import { useOptionsStore } from './store/optionsStore';

import GeneralTab from './components/tabs/GeneralTab';
import StylesTab from './components/tabs/StylesTab';
import ProfilesTab from './components/tabs/ProfilesTab';

const App = (): JSX.Element | null => {
  const activeTab = useOptionsStore((state) => state.activeTab);
  const setActiveTab = useOptionsStore((state) => state.setActiveTab);
  const openCanvas = useOptionsStore((state) => state.openCanvas);
  const strings = useOptionsStore((state) => state.strings);
  const options = useOptionsStore((state) => state.options);
  const appTitle = typeof options.app_name === 'string' && options.app_name !== '' ? (options.app_name as string) : 'Sidebar JLG';

  const tabs = useMemo(
    () => [
      { name: 'general', title: strings?.generalTab ?? 'Général', className: 'sidebar-jlg-tab-general' },
      { name: 'styles', title: strings?.stylesTab ?? 'Styles', className: 'sidebar-jlg-tab-styles' },
      { name: 'profiles', title: strings?.profilesTab ?? 'Profils', className: 'sidebar-jlg-tab-profiles' },
    ],
    [strings]
  );

  if (!strings) {
    return null;
  }

  return (
    <Fragment>
      <OnboardingModal />
      <Panel className="sidebar-jlg-admin-panel">
        <div className="sidebar-jlg-admin-panel__header">
          <div>
            <h1 className="sidebar-jlg-admin-panel__title">{appTitle}</h1>
            <p className="sidebar-jlg-admin-panel__subtitle">{strings.onboardingDescription}</p>
          </div>
          <Button variant="primary" onClick={openCanvas}>
            {strings.openCanvas}
          </Button>
        </div>
        <TabPanel
          className="sidebar-jlg-admin-tabs"
          activeClass="is-active"
          tabs={tabs}
          onSelect={(tab) => setActiveTab(tab as 'general' | 'styles' | 'profiles')}
          initialTabName={activeTab}
        >
          {(tab) => {
            switch (tab.name) {
              case 'styles':
                return <StylesTab />;
              case 'profiles':
                return <ProfilesTab />;
              case 'general':
              default:
                return <GeneralTab />;
            }
          }}
        </TabPanel>
      </Panel>
      <PreviewCanvas />
    </Fragment>
  );
};

export default App;
