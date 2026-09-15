import { Fragment } from 'react';

import OnboardingModal from './components/OnboardingModal';
import PreviewCanvas from './components/PreviewCanvas';
import { useOptionsStore } from './store/optionsStore';

const App = (): JSX.Element | null => {
  const strings = useOptionsStore((state) => state.strings);

  if (!strings) {
    return null;
  }

  return (
    <Fragment>
      <OnboardingModal />
      <PreviewCanvas />
    </Fragment>
  );
};

export default App;
