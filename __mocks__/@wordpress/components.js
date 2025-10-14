const React = require('react');

const Button = ({ children, onClick, type = 'button', ...props }) =>
  React.createElement('button', { type, onClick, ...props }, children);

const Modal = ({ title, children, onRequestClose }) =>
  React.createElement(
    'div',
    { role: 'dialog' },
    React.createElement('h2', null, title),
    typeof onRequestClose === 'function'
      ? React.createElement('button', { type: 'button', onClick: onRequestClose }, 'close')
      : null,
    children
  );

const Notice = ({ children }) => React.createElement('div', null, children);
const Panel = ({ children }) => React.createElement('div', null, children);
const PanelBody = ({ children }) => React.createElement('div', null, children);
const Spinner = () => React.createElement('div', null, 'spinner');

const TabPanel = ({ tabs = [], initialTabName, children }) => {
  const active = tabs.find((tab) => tab.name === initialTabName) || tabs[0] || { name: '', title: '' };
  return React.createElement('div', null, typeof children === 'function' ? children(active) : children);
};

const ColorPicker = ({ color = '#000000', onChangeComplete }) =>
  React.createElement('input', {
    type: 'color',
    value: color,
    onChange: (event) => onChangeComplete && onChangeComplete(event.target.value),
  });

const RangeControl = ({ value = 0, min = 0, max = 100, onChange, label }) =>
  React.createElement(
    'label',
    null,
    label,
    React.createElement('input', {
      type: 'range',
      value,
      min,
      max,
      onChange: (event) => onChange && onChange(Number(event.target.value)),
    })
  );

const ToggleControl = ({ checked = false, onChange, label }) =>
  React.createElement(
    'label',
    null,
    React.createElement('input', {
      type: 'checkbox',
      checked,
      onChange: (event) => onChange && onChange(event.target.checked),
    }),
    label
  );

const TextControl = ({ value = '', onChange, label }) =>
  React.createElement(
    'label',
    null,
    label,
    React.createElement('input', {
      type: 'text',
      value,
      onChange: (event) => onChange && onChange(event.target.value),
    })
  );

const Card = ({ children }) => React.createElement('div', null, children);
const CardHeader = ({ children }) => React.createElement('div', null, children);
const CardBody = ({ children }) => React.createElement('div', null, children);

const ProgressBar = ({ label }) => React.createElement('div', null, label || '');

module.exports = {
  Button,
  Modal,
  Notice,
  Panel,
  PanelBody,
  TabPanel,
  ColorPicker,
  RangeControl,
  ToggleControl,
  TextControl,
  Spinner,
  Card,
  CardHeader,
  CardBody,
  ProgressBar,
};
