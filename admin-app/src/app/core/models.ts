export interface User {
  '@id'?: string;
  id?: number;
  email: string;
  name?: string;
  roles: string[];
  updatedAt?: string;
  /** Write-only: accepted on create/update, never returned by the API. */
  plainPassword?: string;
}

export type BlockType = 'rich_text' | 'image' | 'hero' | 'text_image' | 'cta' | 'pricing_table';

export interface Block {
  '@id'?: string;
  id?: number;
  type: BlockType | string;
  position: number;
  data: Record<string, unknown>;
  /** Optional in-page anchor (HTML id) for menu links like #section */
  anchor?: string | null;
  imageUrl?: string | null;
  /** IRI of the owning article, e.g. /api/articles/5 (write only) */
  page?: string;
}

export interface Article {
  '@id'?: string;
  id?: number;
  slug: string;
  title: string;
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
  available_pages: PageOption[];
}

export type SettingsPatch = Partial<Pick<Settings, 'home_page_slug' | 'site_name' | 'brand_logo'>>;

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
