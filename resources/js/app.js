import "@fontsource-variable/inter";
import "@fontsource/noto-sans-bengali/400.css";
import "@fontsource/noto-sans-bengali/500.css";
import "@fontsource/noto-sans-bengali/600.css";
import "@fontsource/noto-sans-bengali/700.css";

import Alpine from "alpinejs";
import { requestJson } from "./http";

window.Alpine = Alpine;
window.requestJson = requestJson;

Alpine.start();
