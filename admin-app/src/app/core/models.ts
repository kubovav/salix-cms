export interface User {
  '@id'?: string;
  id?: number;
  email: string;
  name?: string;
  roles: string[];
  updatedAt?: string;
  plainPassword?: string;
}

export type BlockType = 'rich_text' | 'image' | 'hero' | 'text_image' | 'cta' | 'pricing_table';

export interface Block {
  '@id'?: string;
  id?: number;
  name?: string | null;
  type: BlockType | string;
  position: number;
  data: Record<string, unknown>;
  anchor?: string | null;
  imageUrl?: string | null;
  renderedHtml?: string | null;
  page?: string;
}

export interface Article {
  '@id'?: string;
  id?: number;
  slug: string;
  title: string;
  metaDescription?: string | null;
  published: boolean;
  updatedAt?: string;
  blockCount?: number;
  blocks?: Block[];
}

export interface MenuItemRef {
  '@id'?: string;
  id?: number;
}

export interface MenuItem {
  '@id'?: string;
  id?: number;
  label: string;
  url?: string | null;
  menuName: string;
  position: number;
  enabled: boolean;
  /** IRI when writing, nested object when reading */
  page?: string | MenuItemRef | null;
  parent?: MenuItemRef | string | null;
  children?: string[];
}

/** UI helper: a menu item with resolved children for tree rendering */
export interface MenuTreeNode extends MenuItem {
  treeChildren: MenuTreeNode[];
}

export interface PageOption {
  slug: string;
  title: string;
}

export interface Settings {
  home_page_slug: string | null;
  site_name: string | null;
  brand_logo: string | null;
  meta_description: string | null;
  available_pages: PageOption[];
}

export type SettingsPatch = Partial<
  Pick<Settings, 'home_page_slug' | 'site_name' | 'brand_logo' | 'meta_description'>
>;

export interface BlockTypeOption {
  value: string;
  label: string;
}

export interface Meta {
  blockTypes: BlockTypeOption[];
  menuNames: string[];
}

export interface UploadResult {
  filename: string;
  publicPath: string;
}
