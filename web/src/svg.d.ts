// SVGs are loaded with @svgr (next.config.ts): the default export is a React component.
declare module "*.svg" {
  import type * as React from "react";

  const Component: React.FC<React.SVGProps<SVGSVGElement> & { title?: string }>;
  export default Component;
}
