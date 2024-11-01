import { decodeEntities } from "@wordpress/html-entities";
import logoSrc from "./logo.png?url";

const { registerPaymentMethod } = window.wc.wcBlocksRegistry;
const { getSetting } = window.wc.wcSettings;

const settings = getSetting("addpay_data", {});

const label = decodeEntities(settings.title);

const Content = () => {
  return <img src={logoSrc} alt="AddPay"/>;
};

const Label = (props) => {
  const { PaymentMethodLabel } = props.components;
  return <PaymentMethodLabel text={label} />;
};

registerPaymentMethod({
  name: "addpay",
  label: <Label />,
  content: <Content />,
  edit: <Content />,
  canMakePayment: () => true,
  ariaLabel: label,
  supports: {
    features: settings.supports,
  },
});
