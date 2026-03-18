import { marked } from 'marked';
import DOMPurify from 'dompurify';

marked.use({ breaks: true, gfm: true });

window.marked = marked;
window.DOMPurify = DOMPurify;
