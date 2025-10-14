import { PanelBody, ColorPicker, RangeControl } from '@wordpress/components';

import { useOptionsStore } from '../../store/optionsStore';

const StylesTab = (): JSX.Element => {
  const options = useOptionsStore((state) => state.options);
  const setOption = useOptionsStore((state) => state.setOption);

  const bgColor = (options.bg_color as string) ?? '#18181b';
  const accentColor = (options.accent_color as string) ?? '#0d6efd';
  const fontColor = (options.font_color as string) ?? '#e0e0e0';
  const widthDesktop = Number(options.width_desktop ?? 280);

  return (
    <div className="sidebar-jlg-tab sidebar-jlg-tab--styles">
      <PanelBody title="Palette" initialOpen>
        <div className="sidebar-jlg-color-control">
          <span className="sidebar-jlg-color-control__label">Fond</span>
          <ColorPicker
            color={bgColor}
            onChangeComplete={(value: string | { hex?: string }) => {
              const colorValue = typeof value === 'string' ? value : value.hex ?? bgColor;
              setOption('bg_color', colorValue);
            }}
          />
        </div>
        <div className="sidebar-jlg-color-control">
          <span className="sidebar-jlg-color-control__label">Accent</span>
          <ColorPicker
            color={accentColor}
            onChangeComplete={(value: string | { hex?: string }) => {
              const colorValue = typeof value === 'string' ? value : value.hex ?? accentColor;
              setOption('accent_color', colorValue);
            }}
          />
        </div>
        <div className="sidebar-jlg-color-control">
          <span className="sidebar-jlg-color-control__label">Texte</span>
          <ColorPicker
            color={fontColor}
            onChangeComplete={(value: string | { hex?: string }) => {
              const colorValue = typeof value === 'string' ? value : value.hex ?? fontColor;
              setOption('font_color', colorValue);
            }}
          />
        </div>
      </PanelBody>
      <PanelBody title="Dimensions" initialOpen>
        <RangeControl
          label="Largeur bureau"
          value={widthDesktop}
          onChange={(value) => setOption('width_desktop', Number(value ?? widthDesktop))}
          min={200}
          max={480}
        />
      </PanelBody>
    </div>
  );
};

export default StylesTab;
