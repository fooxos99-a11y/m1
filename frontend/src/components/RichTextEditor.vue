<template>
  <div
    class="rich-text-editor"
    :class="{ 'rich-text-editor--disabled': disabled }"
    :style="editorStyle"
  >
    <div
      ref="toolbar"
      class="rich-text-editor__toolbar"
    >
      <span class="ql-formats">
        <AppNativeSelect
          class="ql-font"
          default-value=""
        >
          <option
            selected
            value=""
          >الخط</option>
          <option value="traditional-arabic">العربي التقليدي</option>
          <option value="simplified-arabic">العربي المبسط</option>
          <option value="arabic-typesetting">صف الحروف العربي</option>
          <option value="tahoma">تاهوما</option>
          <option value="arial">آريال</option>
          <option value="segoe-ui">سيغو UI</option>
        </AppNativeSelect>
      </span>
      <span class="ql-formats">
        <AppRawButton
          class="ql-bold"
        />
        <AppRawButton
          class="ql-italic"
        />
        <AppRawButton
          class="ql-underline"
        />
      </span>
      <span class="ql-formats">
        <AppRawButton
          class="ql-list"
          value="ordered"
        />
        <AppRawButton
          class="ql-list"
          value="bullet"
        />
        <AppRawButton
          class="ql-blockquote"
        />
        <AppRawButton
          class="ql-table rich-text-editor__table-trigger"
          value="newtable_3_3"
          title="إدراج جدول 3 × 3"
          aria-label="إدراج جدول 3 × 3"
        >
          <span
            class="rich-text-editor__table-trigger-icon"
            aria-hidden="true"
          >▦</span>
        </AppRawButton>
      </span>
      <span class="ql-formats">
        <AppRawButton
          class="ql-indent"
          value="-1"
        />
        <AppRawButton
          class="ql-indent"
          value="+1"
        />
        <AppRawButton
          class="ql-link"
        />
        <AppRawButton
          v-if="!lockImages"
          class="ql-image"
        />
      </span>
      <span class="ql-formats">
        <AppRawButton
          class="ql-clean"
        />
      </span>
      <span class="ql-formats">
        <AppRawButton
          class="ql-undo"
          title="تراجع (Ctrl+Z)"
          aria-label="تراجع"
        >
          <svg
            viewBox="0 0 18 18"
            xmlns="http://www.w3.org/2000/svg"
          >
            <path
              class="ql-stroke"
              fill="none"
              stroke-linecap="round"
              stroke-linejoin="round"
              stroke-width="1.5"
              d="M4 8.5A4.5 4.5 0 1 1 4 14"
            />
            <polyline
              class="ql-stroke"
              fill="none"
              stroke-linecap="round"
              stroke-linejoin="round"
              stroke-width="1.5"
              points="2,6 4,8.5 6.5,6.5"
            />
          </svg>
        </AppRawButton>
      </span>
    </div>

    <input
      ref="imageInput"
      class="rich-text-editor__image-input"
      type="file"
      accept="image/png,image/jpeg,image/gif,image/webp"
      @change="handleImageSelection"
    >

    <div
      ref="surface"
      class="rich-text-editor__surface"
      :class="{ 'rich-text-editor__surface--dragging': isDragOver }"
      @dragover.prevent="handleSurfaceDragOver"
      @dragleave="handleSurfaceDragLeave"
      @drop.prevent="handleSurfaceDrop"
    >
      <div
        v-if="isDragOver"
        class="rich-text-editor__drop-hint"
      >
        أفلِت الصورة هنا لإضافتها داخل الصفحة
      </div>
      <div
        v-if="selectedImageFrame"
        class="rich-text-editor__image-frame"
        :style="selectedImageFrameStyle"
        @pointerdown.prevent="handleImageFramePointerDown"
      >
        <AppRawButton
          v-for="handle in imageHandles"
          :key="handle"
          class="rich-text-editor__image-handle"
          :class="`rich-text-editor__image-handle--${handle}`"
          @pointerdown.prevent="startImageResize(handle, $event)"
        />
      </div>
      <div
        v-if="tableContextMenu.visible"
        ref="tableContextMenu"
        class="rich-text-editor__table-menu"
        :style="tableContextMenuStyle"
      >
        <AppRawButton
          v-for="action in tableContextActions"
          :key="action.value"
          class="rich-text-editor__table-menu-item"
          :class="{ 'rich-text-editor__table-menu-item--danger': action.danger }"
          @click="handleTableContextAction(action.value)"
        >
          {{ action.label }}
        </AppRawButton>
      </div>
      <div
        ref="editor"
        class="rich-text-editor__editor"
      />
    </div>
  </div>
</template>

<script>
import Quill from 'quill';
import 'quill/dist/quill.snow.css';
import TableModule from 'quill1-table';
import { uploadEditorImage } from '@/services/api';
import { sanitizeRichTextHtml } from '@/utils/documentContent';
import AppNativeSelect from './AppNativeSelect.vue';
import { AppRawButton } from './ui';

const FontFormat = Quill.import('formats/font');
const Delta = Quill.import('delta');
const BaseImageFormat = Quill.import('formats/image');
const IMAGE_EMBED_ATTRIBUTES = ['data-width-px', 'data-height-px', 'data-x', 'data-y'];
const MIN_IMAGE_DIMENSION = 80;

class RichImageFormat extends BaseImageFormat {
  static formats(domNode) {
    const formats = typeof super.formats === 'function'
      ? super.formats(domNode) || {}
      : {};

    IMAGE_EMBED_ATTRIBUTES.forEach((attributeName) => {
      if (domNode.hasAttribute(attributeName)) {
        formats[attributeName] = domNode.getAttribute(attributeName);
      }
    });

    return formats;
  }

  format(name, value) {
    if (IMAGE_EMBED_ATTRIBUTES.includes(name)) {
      if (value || value === 0 || value === '0') {
        this.domNode.setAttribute(name, String(value));
      } else {
        this.domNode.removeAttribute(name);
      }

      return;
    }

    super.format(name, value);
  }
}

FontFormat.whitelist = [
  'traditional-arabic',
  'simplified-arabic',
  'arabic-typesetting',
  'tahoma',
  'arial',
  'segoe-ui',
];

Quill.register(FontFormat, true);
Quill.register(RichImageFormat, true);
Quill.register('modules/table', TableModule, true);

const createTableKeyboardBindings = () => ({
  enterBeforeTable: {
    key: 'enter',
    collapsed: true,
    handler(range, keycontext) {
      if (!range || range.length > 0 || keycontext.offset !== 0) {
        return true;
      }

      const [leaf] = this.quill.getLeaf(range.index);
      const domNode = leaf?.domNode?.nodeType === 3
        ? leaf.domNode.parentElement
        : leaf?.domNode;
      const tableCell = domNode?.closest?.('td, th');

      if (!tableCell) {
        return true;
      }

      const tableElement = tableCell.closest('table');
      const firstCell = tableElement?.querySelector('td, th');

      if (!tableElement || firstCell !== tableCell) {
        return true;
      }

      const tableBlot = Quill.find(tableElement, true);

      if (!tableBlot) {
        return true;
      }

      const tableIndex = this.quill.getIndex(tableBlot);

      this.quill.insertText(tableIndex, '\n', Quill.sources.USER);
      this.quill.setSelection(tableIndex, 0, Quill.sources.SILENT);
      return false;
    },
  },
  tab: {
    key: 'tab',
    handler(range, keycontext) {
      const isOutsideTable = TableModule.keyboardHandler(this.quill, 'tab', range, keycontext);

      if (isOutsideTable) {
        this.quill.history.cutoff();
        const delta = new Delta().retain(range.index).delete(range.length).insert('\t');
        this.quill.updateContents(delta, Quill.sources.USER);
        this.quill.history.cutoff();
        this.quill.setSelection(range.index + 1, Quill.sources.SILENT);
      }

      return false;
    },
  },
  shiftTab: {
    key: 'tab',
    shiftKey: true,
    handler(range, keycontext) {
      return TableModule.keyboardHandler(this.quill, 'shiftTab', range, keycontext);
    },
  },
  selectAll: {
    key: 'a',
    shortKey: true,
    handler(range, keycontext) {
      return TableModule.keyboardHandler(this.quill, 'selectAll', range, keycontext);
    },
  },
  backspace: {
    key: 'backspace',
    handler(range, keycontext) {
      return TableModule.keyboardHandler(this.quill, 'backspace', range, keycontext);
    },
  },
  delete: {
    key: 'delete',
    handler(range, keycontext) {
      return TableModule.keyboardHandler(this.quill, 'delete', range, keycontext);
    },
  },
  undo: {
    shortKey: true,
    key: 'z',
    handler(range, keycontext) {
      return TableModule.keyboardHandler(this.quill, 'undo', range, keycontext);
    },
  },
  redo: {
    shortKey: true,
    shiftKey: true,
    key: 'z',
    handler(range, keycontext) {
      return TableModule.keyboardHandler(this.quill, 'redo', range, keycontext);
    },
  },
  copy: {
    shortKey: true,
    key: 'c',
    handler(range, keycontext) {
      return TableModule.keyboardHandler(this.quill, 'copy', range, keycontext);
    },
  },
});

const TABLE_CONTEXT_ACTIONS = [
  {
    value: 'append-row-below',
    label: 'إضافة صف',
  },
  {
    value: 'append-col-after',
    label: 'إضافة عمود',
  },
  {
    value: 'remove-row',
    label: 'إزالة صف',
  },
  {
    value: 'remove-col',
    label: 'إزالة عمود',
  },
  {
    value: 'remove-table',
    label: 'حذف الجدول',
    danger: true,
  },
];

const replaceNodeWithText = (node, documentRef) => {
  if (!node?.parentNode) {
    return;
  }

  node.parentNode.replaceChild(documentRef.createTextNode(node.textContent || ''), node);
};

export default {
  name: 'RichTextEditor',
  components: {
    AppNativeSelect,
    AppRawButton,
  },
  props: {
    value: {
      type: String,
      default: '',
    },
    protectedContent: {
      type: String,
      default: '',
    },
    placeholder: {
      type: String,
      default: '',
    },
    disabled: {
      type: Boolean,
      default: false,
    },
    lockImages: {
      type: Boolean,
      default: false,
    },
    allowProtectedEditing: {
      type: Boolean,
      default: false,
    },
    minHeight: {
      type: String,
      default: '320px',
    },
  },
  data() {
    return {
      editor: null,
      isApplyingExternalValue: false,
      isUploadingImage: false,
      isDragOver: false,
      selectedImage: null,
      selectedImageFrame: null,
      imageInteraction: null,
      pendingImageInteractionPoint: null,
      imageInteractionFrameRequest: null,
      imageInteractionMoveListener: null,
      imageInteractionStopListener: null,
      toolbarPointerDownListener: null,
      lastSelectionRange: null,
      protectedBoundaryIndex: 0,
      tableContextMenu: {
        visible: false,
        top: 0,
        left: 0,
      },
    };
  },
  computed: {
    editorStyle() {
      return {
        '--rich-text-editor-min-height': this.minHeight,
      };
    },
    imageHandles() {
      return ['nw', 'ne', 'sw', 'se'];
    },
    selectedImageFrameStyle() {
      if (!this.selectedImageFrame) {
        return null;
      }

      return {
        top: `${this.selectedImageFrame.top}px`,
        left: `${this.selectedImageFrame.left}px`,
        width: `${this.selectedImageFrame.width}px`,
        height: `${this.selectedImageFrame.height}px`,
      };
    },
    tableContextActions() {
      return TABLE_CONTEXT_ACTIONS;
    },
    tableContextMenuStyle() {
      if (!this.tableContextMenu.visible) {
        return null;
      }

      return {
        top: `${this.tableContextMenu.top}px`,
        left: `${this.tableContextMenu.left}px`,
      };
    },
  },
  watch: {
    value(nextValue) {
      if (!this.editor || this.isApplyingExternalValue) {
        return;
      }

      const currentValue = this.editor.root.innerHTML;
      const sanitizedValue = this.sanitizeEditorHtml(nextValue || '');

      if (currentValue === sanitizedValue) {
        return;
      }

      this.isApplyingExternalValue = true;
      this.setEditorHtml(sanitizedValue);
      this.$nextTick(() => {
        this.isApplyingExternalValue = false;
      });
    },
    disabled(nextValue) {
      if (this.editor) {
        this.editor.enable(!nextValue);
      }
    },
    protectedContent() {
      if (!this.editor) {
        return;
      }

      this.updateProtectedBoundary();
      this.ensureEditableTailForProtectedContent();
      this.ensureSelectionOutsideProtected();
      this.clearSelectedImage();
    },
  },
  mounted() {
    this.initializeEditor();
  },
  beforeDestroy() {
    this.destroyEditor();
  },
  methods: {
    initializeEditor() {
      const resolvedPlaceholder = typeof this.placeholder === 'string' && this.placeholder.length > 0
        ? this.placeholder
        : ' ';

      this.editor = new Quill(this.$refs.editor, {
        theme: 'snow',
        placeholder: resolvedPlaceholder,
        modules: {
          toolbar: {
            container: this.$refs.toolbar,
            handlers: {
              image: () => this.openImagePicker(),
              table: (value) => this.handleTableToolbarAction(value),
              undo: () => this.editor.history.undo(),
            },
          },
          table: {
            cellSelectionOnClick: true,
          },
          history: {
            delay: 500,
          },
          keyboard: {
            bindings: createTableKeyboardBindings(),
          },
          clipboard: {
            matchVisual: false,
          },
        },
      });

      this.editor.root.setAttribute('dir', 'rtl');
      this.editor.root.setAttribute('lang', 'ar');
      this.setEditorHtml(this.sanitizeEditorHtml(this.value || ''));
      this.updateProtectedBoundary();
      this.editor.enable(!this.disabled);
      this.imageInteractionMoveListener = (event) => this.handleImageInteractionMove(event);
      this.imageInteractionStopListener = () => this.stopImageInteraction();
      this.toolbarPointerDownListener = (event) => this.handleToolbarPointerDown(event);
      this.editor.root.addEventListener('click', this.handleEditorClick);
      this.editor.root.addEventListener('pointerdown', this.handleEditorPointerDown);
      this.editor.root.addEventListener('paste', this.handleEditorPaste);
      this.editor.root.addEventListener('contextmenu', this.handleTableContextMenu);
      this.$refs.toolbar?.addEventListener('pointerdown', this.toolbarPointerDownListener, true);
      window.addEventListener('resize', this.handleViewportChange);
      window.addEventListener('scroll', this.handleViewportChange, true);
      window.addEventListener('pointerdown', this.handleWindowPointerDown, true);

      this.editor.on('text-change', this.handleEditorTextChange);
      this.editor.on('selection-change', this.handleSelectionChange);
    },
    rememberSelection(range) {
      if (!range) {
        return;
      }

      this.lastSelectionRange = {
        index: range.index,
        length: range.length || 0,
      };
    },
    restoreLastSelection() {
      if (!this.editor || !this.lastSelectionRange) {
        return;
      }

      this.editor.focus();
      this.editor.setSelection(
        this.lastSelectionRange.index,
        this.lastSelectionRange.length || 0,
        Quill.sources.SILENT,
      );
    },
    sanitizeEditorHtml(value, options = {}) {
      const { disallowTables = false } = options;
      const sanitizedHtml = sanitizeRichTextHtml(value || '');

      if (typeof document === 'undefined') {
        return sanitizedHtml;
      }

      const template = document.createElement('template');
      template.innerHTML = sanitizedHtml;

      Array.from(template.content.querySelectorAll('table img')).forEach((imageNode) => {
        imageNode.remove();
      });

      const tableSelector = disallowTables ? 'table' : 'table table';
      Array.from(template.content.querySelectorAll(tableSelector)).forEach((tableNode) => {
        replaceNodeWithText(tableNode, document);
      });

      const container = document.createElement('div');
      container.appendChild(template.content);
      return container.innerHTML;
    },
    setEditorHtml(value) {
      if (!this.editor) {
        return;
      }

      this.editor.clipboard.dangerouslyPasteHTML(value || '');
      this.$nextTick(() => {
        this.refreshEditorImages();
        this.updateProtectedBoundary();
        this.ensureEditableTailForProtectedContent();
        this.ensureSelectionOutsideProtected();
      });
    },
    calculateProtectedBoundary() {
      if (!this.editor) {
        return 0;
      }

      const sanitizedProtectedContent = this.sanitizeEditorHtml(this.protectedContent || '');

      if (!sanitizedProtectedContent) {
        return 0;
      }

      const protectedDelta = this.editor.clipboard.convert(sanitizedProtectedContent);
      return Math.max(0, protectedDelta.length());
    },
    updateProtectedBoundary() {
      this.protectedBoundaryIndex = this.calculateProtectedBoundary();
    },
    getEffectiveProtectedBoundary() {
      return this.allowProtectedEditing ? 0 : this.protectedBoundaryIndex;
    },
    ensureEditableTailForProtectedContent() {
      const protectedBoundaryIndex = this.getEffectiveProtectedBoundary();

      if (!this.editor || protectedBoundaryIndex <= 0) {
        return;
      }

      if (this.editor.getLength() <= protectedBoundaryIndex) {
        this.editor.insertText(this.editor.getLength() - 1, '\n', Quill.sources.SILENT);
      }
    },
    isProtectedIndex(index) {
      if (this.allowProtectedEditing) {
        return false;
      }

      return this.protectedBoundaryIndex > 0 && Number.isFinite(index) && index < this.protectedBoundaryIndex;
    },
    doesRangeTouchProtected(range) {
      if (this.allowProtectedEditing) {
        return false;
      }

      if (!range || this.protectedBoundaryIndex <= 0) {
        return false;
      }

      const rangeEnd = range.index + Math.max(range.length, 0);
      return range.index < this.protectedBoundaryIndex || rangeEnd < this.protectedBoundaryIndex;
    },
    ensureSelectionOutsideProtected() {
      if (this.allowProtectedEditing) {
        return;
      }

      if (!this.editor || this.protectedBoundaryIndex <= 0) {
        return;
      }

      const selection = this.editor.getSelection();

      if (!selection || !this.doesRangeTouchProtected(selection)) {
        return;
      }

      this.editor.setSelection(this.protectedBoundaryIndex, 0, Quill.sources.SILENT);
    },
    resolveEditableInsertIndex(range = this.editor?.getSelection(true)) {
      if (!this.editor) {
        return 0;
      }

      const protectedBoundaryIndex = this.getEffectiveProtectedBoundary();

      const preferredIndex = Number.isFinite(range?.index)
        ? range.index
        : Math.max(0, this.editor.getLength() - 1);

      return Math.max(protectedBoundaryIndex, preferredIndex);
    },
    doesDeltaModifyProtectedContent(delta) {
      if (this.allowProtectedEditing) {
        return false;
      }

      if (!delta?.ops?.length || this.protectedBoundaryIndex <= 0) {
        return false;
      }

      let documentIndex = 0;

      return delta.ops.some((operation) => {
        if (typeof operation.retain === 'number') {
          const retainStart = documentIndex;
          const retainEnd = documentIndex + operation.retain;
          documentIndex = retainEnd;

          return Boolean(
            operation.attributes
            && retainStart < this.protectedBoundaryIndex
            && retainEnd > 0,
          );
        }

        if (typeof operation.insert !== 'undefined') {
          return documentIndex < this.protectedBoundaryIndex;
        }

        if (typeof operation.delete === 'number') {
          const deleteStart = documentIndex;
          documentIndex += operation.delete;

          return deleteStart < this.protectedBoundaryIndex;
        }

        return false;
      });
    },
    collectImageEmbedSignatures(delta) {
      if (!delta?.ops?.length) {
        return [];
      }

      const signatures = [];

      delta.ops.forEach((operation) => {
        if (typeof operation.insert === 'string') {
          return;
        }

        if (operation.insert && typeof operation.insert === 'object' && operation.insert.image) {
          signatures.push({
            src: String(operation.insert.image || ''),
            width: operation.attributes?.['data-width-px'] || null,
            height: operation.attributes?.['data-height-px'] || null,
            x: operation.attributes?.['data-x'] || null,
            y: operation.attributes?.['data-y'] || null,
          });
        }
      });

      return signatures;
    },
    didUserModifyImages(previousDelta) {
      if (!this.editor || !previousDelta) {
        return false;
      }

      const previousImages = this.collectImageEmbedSignatures(previousDelta);
      const nextImages = this.collectImageEmbedSignatures(this.editor.getContents());

      return JSON.stringify(previousImages) !== JSON.stringify(nextImages);
    },
    handleEditorTextChange(delta, oldDelta, source) {
      if (this.isApplyingExternalValue) {
        return;
      }

      if (source === Quill.sources.USER && this.lockImages && this.didUserModifyImages(oldDelta)) {
        this.isApplyingExternalValue = true;
        this.editor.setContents(oldDelta, Quill.sources.SILENT);
        this.$nextTick(() => {
          this.refreshEditorImages();
          this.updateProtectedBoundary();
          this.ensureEditableTailForProtectedContent();
          this.ensureSelectionOutsideProtected();
          this.isApplyingExternalValue = false;
        });
        return;
      }

      if (source === Quill.sources.USER && this.doesDeltaModifyProtectedContent(delta)) {
        this.isApplyingExternalValue = true;
        this.editor.setContents(oldDelta, Quill.sources.SILENT);
        this.$nextTick(() => {
          this.refreshEditorImages();
          this.updateProtectedBoundary();
          this.ensureSelectionOutsideProtected();
          this.isApplyingExternalValue = false;
        });
        return;
      }

      this.$nextTick(() => {
        this.refreshEditorImages();
        this.updateSelectedImageFrame();
        this.updateProtectedBoundary();
        this.ensureEditableTailForProtectedContent();
        this.ensureSelectionOutsideProtected();
      });

      if (source === Quill.sources.USER) {
        this.emitEditorValue();
      }
    },
    openImagePicker() {
      if (this.lockImages || this.disabled || this.isUploadingImage || !this.$refs.imageInput) {
        return;
      }

      const editableInsertIndex = this.resolveEditableInsertIndex();

      this.editor.focus();
      this.editor.setSelection(editableInsertIndex, 0, Quill.sources.SILENT);

      if (this.isSelectionInsideTable()) {
        this.showTableRestrictionToast('image');
        return;
      }

      this.$refs.imageInput.value = '';
      this.$refs.imageInput.click();
    },
    runTableAction(value) {
      if (!value || !this.editor) {
        return false;
      }

      const toolbar = this.editor.getModule('toolbar');
      const tableHandler = toolbar?.handlers?.table;

      if (typeof tableHandler !== 'function') {
        return false;
      }

      tableHandler.call(toolbar, value);
      return true;
    },
    handleTableToolbarAction(value) {
      if (!value || !this.editor) {
        return;
      }

      const editableInsertIndex = this.resolveEditableInsertIndex();

      this.editor.focus();
      this.editor.setSelection(editableInsertIndex, 0, Quill.sources.SILENT);

      if (value.startsWith('newtable_')) {
        const selection = this.editor.getSelection(true);
        const shouldRelocateSelection = !selection || this.isSelectionInsideTable(selection);

        if (shouldRelocateSelection) {
          const protectedBoundaryIndex = this.getEffectiveProtectedBoundary();
          const tableExitIndex = Math.max(
            protectedBoundaryIndex,
            Math.max(0, this.editor.getLength() - 1),
          );

          this.editor.focus();
          this.editor.setSelection(tableExitIndex, 0, Quill.sources.SILENT);
          window.requestAnimationFrame(() => {
            this.runTableAction(value);
          });
          return;
        }
      }

      this.runTableAction(value);
    },
    async handleImageSelection(event) {
      if (this.lockImages) {
        return;
      }

      const [file] = event?.target?.files || [];

      if (!file || !this.editor) {
        return;
      }

      await this.uploadAndInsertImage(file);
    },
    async uploadAndInsertImage(file, insertIndex = null) {
      if (!this.editor || this.isUploadingImage) {
        return;
      }

      this.isUploadingImage = true;

      try {
        const { url } = await uploadEditorImage(file);
        const selection = this.editor.getSelection(true);
        const preferredInsertIndex = Number.isInteger(insertIndex)
          ? insertIndex
          : this.resolveEditableInsertIndex(selection);
        const protectedBoundaryIndex = this.getEffectiveProtectedBoundary();
        const resolvedInsertIndex = Math.max(protectedBoundaryIndex, preferredInsertIndex);

        if (this.isInsertIndexInsideTable(resolvedInsertIndex)) {
          this.showTableRestrictionToast('image');
          return;
        }

        this.editor.insertEmbed(resolvedInsertIndex, 'image', url, 'user');
        this.editor.setSelection(resolvedInsertIndex + 1, 0, 'silent');
        this.$nextTick(() => {
          const [leaf] = this.editor.getLeaf(resolvedInsertIndex);

          if (leaf?.domNode?.tagName === 'IMG') {
            this.prepareImageElement(leaf.domNode);
            this.persistImageMetrics(leaf.domNode);
            this.setSelectedImage(leaf.domNode);
          }

          this.emitEditorValue();
        });

        if (typeof this.$toast?.success === 'function') {
          this.$toast.success('تم رفع الصورة');
        }
      } catch (error) {
        if (typeof this.$toast?.error === 'function') {
          this.$toast.error(error?.response?.data?.message || 'تعذر رفع الصورة');
        }
      } finally {
        this.isUploadingImage = false;

        if (this.$refs.imageInput) {
          this.$refs.imageInput.value = '';
        }
      }
    },
    handleSurfaceDragOver(event) {
      if (this.lockImages || this.disabled || !this.extractImageFile(event.dataTransfer)) {
        return;
      }

      this.isDragOver = true;
    },
    handleSurfaceDragLeave(event) {
      if (event.currentTarget?.contains(event.relatedTarget)) {
        return;
      }

      this.isDragOver = false;
    },
    async handleSurfaceDrop(event) {
      if (this.lockImages || this.disabled) {
        return;
      }

      const imageFile = this.extractImageFile(event.dataTransfer);

      this.isDragOver = false;

      if (!imageFile) {
        return;
      }

      this.editor.focus();
      await this.uploadAndInsertImage(imageFile, this.resolveDropInsertIndex(event));
    },
    async handleEditorPaste(event) {
      if (this.disabled) {
        return;
      }

      const selectionIsInsideTable = this.isSelectionInsideTable();
      const imageFile = this.extractImageFile(event.clipboardData);

      if (imageFile) {
        event.preventDefault();
        if (this.lockImages) {
          return;
        }

        if (selectionIsInsideTable) {
          this.showTableRestrictionToast('image');
          return;
        }

        await this.uploadAndInsertImage(imageFile);
        return;
      }

      const html = event.clipboardData?.getData('text/html') || '';

      if (!html) {
        return;
      }

      const containsTableMarkup = /<table\b/i.test(html);
      const containsImageMarkup = /<img\b/i.test(html);

      if (!selectionIsInsideTable && !containsTableMarkup && !containsImageMarkup) {
        return;
      }

      event.preventDefault();

      if (selectionIsInsideTable && containsTableMarkup) {
        this.showTableRestrictionToast('table');
      }

      if (selectionIsInsideTable && containsImageMarkup) {
        this.showTableRestrictionToast('image');
      }

      const sanitizedHtml = this.sanitizeEditorHtml(html, {
        disallowTables: selectionIsInsideTable,
      });
      const plainText = event.clipboardData?.getData('text/plain') || '';

      if (sanitizedHtml) {
        this.insertHtmlAtSelection(sanitizedHtml);
        return;
      }

      if (plainText) {
        this.insertTextAtSelection(plainText);
      }
    },
    insertHtmlAtSelection(html) {
      if (!this.editor || !html) {
        return;
      }

      const selection = this.editor.getSelection(true);
      const insertIndex = selection ? selection.index : this.editor.getLength();

      if (selection?.length) {
        this.editor.deleteText(selection.index, selection.length, Quill.sources.USER);
      }

      this.editor.clipboard.dangerouslyPasteHTML(insertIndex, html, Quill.sources.USER);
    },
    insertTextAtSelection(value) {
      if (!this.editor || !value) {
        return;
      }

      const selection = this.editor.getSelection(true);
      const insertIndex = selection ? selection.index : this.editor.getLength();

      if (selection?.length) {
        this.editor.deleteText(selection.index, selection.length, Quill.sources.USER);
      }

      this.editor.insertText(insertIndex, value, Quill.sources.USER);
      this.editor.setSelection(insertIndex + value.length, 0, Quill.sources.SILENT);
    },
    extractImageFile(source) {
      const items = Array.from(source?.items || []);
      const files = items
        .filter((item) => item.kind === 'file' && item.type.startsWith('image/'))
        .map((item) => item.getAsFile())
        .filter(Boolean);

      if (files.length > 0) {
        return files[0];
      }

      const fallbackFiles = Array.from(source?.files || []).filter((file) => file.type.startsWith('image/'));

      return fallbackFiles[0] || null;
    },
    resolveDropInsertIndex(event) {
      if (!this.editor) {
        return 0;
      }

      if (typeof document.caretRangeFromPoint === 'function') {
        const range = document.caretRangeFromPoint(event.clientX, event.clientY);

        if (range) {
          const blot = Quill.find(range.startContainer, true);

          if (blot) {
            return this.editor.getIndex(blot);
          }
        }
      }

      if (typeof document.caretPositionFromPoint === 'function') {
        const position = document.caretPositionFromPoint(event.clientX, event.clientY);

        if (position) {
          const blot = Quill.find(position.offsetNode, true);

          if (blot) {
            return this.editor.getIndex(blot);
          }
        }
      }

      const targetBlot = Quill.find(event.target, true);

      if (targetBlot) {
        const targetIndex = this.editor.getIndex(targetBlot);

        return targetBlot.domNode?.tagName === 'IMG' ? targetIndex + 1 : targetIndex;
      }

      const selection = this.editor.getSelection(true);

      return selection ? selection.index : this.editor.getLength();
    },
    handleEditorClick(event) {
      this.hideTableContextMenu();

      const imageElement = event.target?.closest?.('img');

      if (imageElement) {
        if (this.lockImages) {
          this.clearSelectedImage();
          const imageBlot = this.editor ? Quill.find(imageElement, true) : null;
          const imageIndex = imageBlot && this.editor ? this.editor.getIndex(imageBlot) : -1;

          if (this.editor && imageIndex >= 0) {
            this.editor.setSelection(imageIndex + 1, 0, Quill.sources.SILENT);
          }

          return;
        }

        const imageBlot = this.editor ? Quill.find(imageElement, true) : null;
        const imageIndex = imageBlot && this.editor ? this.editor.getIndex(imageBlot) : -1;

        if (this.isProtectedIndex(imageIndex)) {
          this.clearSelectedImage();
          this.ensureSelectionOutsideProtected();
          return;
        }

        this.setSelectedImage(imageElement);
        return;
      }

      this.clearSelectedImage();
    },
    handleEditorPointerDown(event) {
      this.hideTableContextMenu();

      const imageElement = event.target?.closest?.('img');

      if (!imageElement || this.disabled || event.button !== 0) {
        return;
      }

      if (this.lockImages) {
        event.preventDefault();
        this.clearSelectedImage();
        return;
      }

      const imageBlot = this.editor ? Quill.find(imageElement, true) : null;
      const imageIndex = imageBlot && this.editor ? this.editor.getIndex(imageBlot) : -1;

      if (this.isProtectedIndex(imageIndex)) {
        event.preventDefault();
        this.clearSelectedImage();
        this.ensureSelectionOutsideProtected();
        return;
      }

      event.preventDefault();
      this.setSelectedImage(imageElement);
      this.startImageDrag(event);
    },
    handleImageFramePointerDown(event) {
      if (this.lockImages || this.disabled || !this.selectedImage || event.button !== 0) {
        return;
      }

      if (event.target?.closest?.('.rich-text-editor__image-handle')) {
        return;
      }

      this.startImageDrag(event);
    },
    handleSelectionChange(range) {
      if (range && this.doesRangeTouchProtected(range)) {
        this.clearSelectedImage();
        this.ensureSelectionOutsideProtected();
        return;
      }

      if (range) {
        this.rememberSelection(range);
      }

      if (!range || !this.editor || this.selectedImage) {
        return;
      }

      const [leaf] = this.editor.getLeaf(range.index);

      if (leaf?.domNode?.tagName === 'IMG') {
        if (this.lockImages) {
          this.clearSelectedImage();
          this.editor.setSelection(range.index + 1, 0, Quill.sources.SILENT);
          return;
        }

        this.setSelectedImage(leaf.domNode);
      }
    },
    handleToolbarPointerDown(event) {
      if (this.disabled || !this.editor) {
        return;
      }

      const toolbarButton = event.target?.closest?.('button');

      if (!toolbarButton || !this.$refs.toolbar?.contains(toolbarButton)) {
        return;
      }

      event.preventDefault();
      this.restoreLastSelection();
    },
    handleTableContextMenu(event) {
      if (this.disabled || !this.editor) {
        return;
      }

      const tableElement = event.target?.closest?.('table');

      if (!tableElement || !this.editor.root.contains(tableElement)) {
        this.hideTableContextMenu();
        return;
      }

      event.preventDefault();
      this.clearSelectedImage();

      const targetCell = event.target?.closest?.('td, th') || tableElement.querySelector('td, th');
      const blot = targetCell ? Quill.find(targetCell, true) : null;

      if (blot) {
        const index = this.editor.getIndex(blot);

        if (this.isProtectedIndex(index)) {
          this.hideTableContextMenu();
          this.ensureSelectionOutsideProtected();
          return;
        }

        this.editor.focus();
        this.editor.setSelection(index, 0, Quill.sources.SILENT);
      }

      this.openTableContextMenu(event);
    },
    openTableContextMenu(event) {
      const surfaceRect = this.$refs.surface?.getBoundingClientRect();

      if (!surfaceRect) {
        return;
      }

      const menuWidth = 220;
      const menuHeight = (this.tableContextActions.length * 42) + 20;
      const nextLeft = Math.max(12, Math.min(event.clientX - surfaceRect.left, surfaceRect.width - menuWidth - 12));
      const nextTop = Math.max(12, Math.min(event.clientY - surfaceRect.top, surfaceRect.height - menuHeight - 12));

      this.tableContextMenu = {
        visible: true,
        top: nextTop,
        left: nextLeft,
      };
    },
    hideTableContextMenu() {
      if (!this.tableContextMenu.visible) {
        return;
      }

      this.tableContextMenu = {
        visible: false,
        top: 0,
        left: 0,
      };
    },
    handleTableContextAction(actionValue) {
      if (!this.editor || !actionValue) {
        return;
      }

      this.runTableAction(actionValue);
      this.hideTableContextMenu();
      this.editor.focus();
    },
    handleWindowPointerDown(event) {
      if (!this.tableContextMenu.visible) {
        return;
      }

      if (this.$refs.tableContextMenu?.contains(event.target)) {
        return;
      }

      this.hideTableContextMenu();
    },
    handleViewportChange() {
      this.updateSelectedImageFrame();
      this.hideTableContextMenu();
    },
    resolveLeafForIndex(index) {
      if (!this.editor) {
        return null;
      }

      const maxIndex = Math.max(this.editor.getLength() - 1, 0);
      const safeIndex = Math.max(0, Math.min(index, maxIndex));
      let [leaf] = this.editor.getLeaf(safeIndex);

      if (!leaf && safeIndex > 0) {
        [leaf] = this.editor.getLeaf(safeIndex - 1);
      }

      return leaf || null;
    },
    resolveTableForIndex(index) {
      const leaf = this.resolveLeafForIndex(index);
      const domNode = leaf?.domNode?.nodeType === Node.TEXT_NODE
        ? leaf.domNode.parentElement
        : leaf?.domNode;

      return domNode?.closest?.('table') || null;
    },
    resolveIndexAfterCurrentTable(range = this.editor?.getSelection(true)) {
      if (!this.editor || !range) {
        return null;
      }

      const tableElement = this.resolveTableForIndex(range.index);

      if (!tableElement) {
        return null;
      }

      const tableBlot = Quill.find(tableElement, true);

      if (!tableBlot) {
        return null;
      }

      let resolvedIndex = this.editor.getIndex(tableBlot) + tableBlot.length();
      const maxIndex = Math.max(0, this.editor.getLength() - 1);

      while (resolvedIndex <= maxIndex && this.resolveTableForIndex(resolvedIndex)) {
        resolvedIndex += 1;
      }

      if (resolvedIndex > maxIndex) {
        const appendIndex = this.editor.getLength() - 1;
        this.editor.insertText(appendIndex, '\n', Quill.sources.SILENT);
        resolvedIndex = this.editor.getLength() - 1;
      }

      const protectedBoundaryIndex = this.getEffectiveProtectedBoundary();
      return Math.max(protectedBoundaryIndex, resolvedIndex);
    },
    isInsertIndexInsideTable(index) {
      if (!Number.isInteger(index)) {
        return this.isSelectionInsideTable();
      }

      return Boolean(this.resolveTableForIndex(index));
    },
    isSelectionInsideTable(range = this.editor?.getSelection(true)) {
      if (!this.editor || !range) {
        return false;
      }

      const startTable = this.resolveTableForIndex(range.index);
      const endIndex = Math.max(range.index, range.index + Math.max(range.length - 1, 0));
      const endTable = this.resolveTableForIndex(endIndex);

      return Boolean(startTable || endTable);
    },
    showTableRestrictionToast(type) {
      if (typeof this.$toast?.error !== 'function') {
        return;
      }

      if (type === 'table') {
        this.$toast.error('لا يمكن إدراج جدول داخل جدول آخر');
        return;
      }

      this.$toast.error('لا يمكن إدراج صورة داخل الجدول');
    },
    setSelectedImage(imageElement) {
      if (this.lockImages) {
        this.clearSelectedImage();
        return;
      }

      if (this.selectedImage && this.selectedImage !== imageElement) {
        this.selectedImage.classList.remove('ql-rich-image--selected');
      }

      this.prepareImageElement(imageElement);
      this.selectedImage = imageElement;
      this.selectedImage.classList.add('ql-rich-image--selected');
      this.updateSelectedImageFrame();
    },
    clearSelectedImage() {
      if (this.selectedImage) {
        this.selectedImage.classList.remove('ql-rich-image--selected');
      }

      this.selectedImage = null;
      this.selectedImageFrame = null;
      this.imageInteraction = null;
    },
    prepareImageElement(imageElement) {
      if (!imageElement) {
        return;
      }

      const editorRoot = this.editor?.root || null;
      const editorWidth = editorRoot?.clientWidth || imageElement.clientWidth || 480;
      const imageRect = imageElement.getBoundingClientRect();
      const editorRect = editorRoot?.getBoundingClientRect() || null;
      const storedWidth = Number.parseFloat(imageElement.getAttribute('data-width-px'));
      const inlineWidth = imageElement.style.width && imageElement.style.width !== 'auto'
        ? Number.parseFloat(imageElement.style.width)
        : Number.NaN;
      const inlineHeight = imageElement.style.height && imageElement.style.height !== 'auto'
        ? Number.parseFloat(imageElement.style.height)
        : Number.NaN;
      const storedHeight = Number.parseFloat(imageElement.getAttribute('data-height-px'));
      const storedX = Number.parseFloat(imageElement.getAttribute('data-x'));
      const storedY = Number.parseFloat(imageElement.getAttribute('data-y'));
      const inlineLeft = imageElement.style.left
        ? Number.parseFloat(imageElement.style.left)
        : Number.NaN;
      const inlineTop = imageElement.style.top
        ? Number.parseFloat(imageElement.style.top)
        : Number.NaN;
      const hasStoredX = imageElement.hasAttribute('data-x') && Number.isFinite(storedX);
      const hasStoredY = imageElement.hasAttribute('data-y') && Number.isFinite(storedY);
      const fallbackWidth = Math.round(Math.min(editorWidth * 0.6, 420));
      const measuredWidth = Math.round(imageRect.width || imageElement.clientWidth || fallbackWidth);
      const resolvedWidth = Number.isFinite(storedWidth)
        ? storedWidth
        : (Number.isFinite(inlineWidth) ? inlineWidth : measuredWidth);
      const naturalRatio = imageElement.naturalWidth > 0 && imageElement.naturalHeight > 0
        ? imageElement.naturalHeight / imageElement.naturalWidth
        : 1;
      const measuredHeight = Math.round(
        imageRect.height
        || (resolvedWidth * naturalRatio)
        || resolvedWidth,
      );
      const resolvedHeight = Number.isFinite(storedHeight)
        ? storedHeight
        : (Number.isFinite(inlineHeight) ? inlineHeight : measuredHeight);
      const fallbackX = editorRect ? Math.round(imageRect.left - editorRect.left) : 0;
      const fallbackY = editorRect ? Math.round(imageRect.top - editorRect.top) : 0;
      const resolvedX = hasStoredX
        ? storedX
        : (Number.isFinite(inlineLeft) ? inlineLeft : Math.max(0, fallbackX));
      const resolvedY = hasStoredY
        ? storedY
        : (Number.isFinite(inlineTop) ? inlineTop : Math.max(0, fallbackY));
      const normalizedMetrics = this.normalizeImageMetrics({
        width: resolvedWidth,
        height: resolvedHeight,
        translateX: resolvedX,
        translateY: resolvedY,
      });

      imageElement.style.position = 'absolute';
      imageElement.style.margin = '0';
      imageElement.style.left = `${normalizedMetrics.translateX}px`;
      imageElement.style.top = `${normalizedMetrics.translateY}px`;
      imageElement.style.transform = 'none';
      imageElement.style.maxWidth = 'none';
      imageElement.style.width = `${normalizedMetrics.width}px`;
      imageElement.style.height = `${normalizedMetrics.height}px`;
      imageElement.setAttribute('data-width-px', String(normalizedMetrics.width));
      if (Number.isFinite(normalizedMetrics.height)) {
        imageElement.setAttribute('data-height-px', String(normalizedMetrics.height));
      }
      imageElement.setAttribute('data-x', String(normalizedMetrics.translateX));
      imageElement.setAttribute('data-y', String(normalizedMetrics.translateY));
    },
    refreshEditorImages() {
      if (!this.editor?.root) {
        return;
      }

      this.editor.root.querySelectorAll('img').forEach((imageElement) => {
        this.prepareImageElement(imageElement);
      });
    },
    persistImageMetrics(imageElement = this.selectedImage) {
      if (!this.editor || !imageElement) {
        return;
      }

      const imageBlot = Quill.find(imageElement, true);

      if (!imageBlot || typeof imageBlot.format !== 'function') {
        return;
      }

      const metrics = this.readSelectedImageMetrics(imageElement);

      imageBlot.format('data-width-px', String(metrics.width));
      imageBlot.format('data-height-px', String(metrics.height));
      imageBlot.format('data-x', String(metrics.translateX));
      imageBlot.format('data-y', String(metrics.translateY));
    },
    syncImageElementMetrics(imageElement, metrics) {
      if (!imageElement || !metrics) {
        return;
      }

      imageElement.style.position = 'absolute';
      imageElement.style.margin = '0';
      imageElement.style.left = `${metrics.translateX}px`;
      imageElement.style.top = `${metrics.translateY}px`;
      imageElement.style.width = `${metrics.width}px`;
      imageElement.style.height = `${metrics.height}px`;
      imageElement.style.transform = 'none';
      imageElement.setAttribute('data-width-px', String(metrics.width));
      imageElement.setAttribute('data-height-px', String(metrics.height));
      imageElement.setAttribute('data-x', String(metrics.translateX));
      imageElement.setAttribute('data-y', String(metrics.translateY));
    },
    resolveLiveImageElement(imageIndex, previousImage = this.selectedImage, forcedMetrics = null) {
      if (!this.editor?.root || !previousImage) {
        return null;
      }

      const previousSrc = previousImage.getAttribute('src') || '';
      const resolvedMetrics = forcedMetrics || this.readSelectedImageMetrics(previousImage);
      const previousWidth = resolvedMetrics.width || 0;
      const previousHeight = resolvedMetrics.height || 0;
      const previousX = resolvedMetrics.translateX || 0;
      const previousY = resolvedMetrics.translateY || 0;
      const currentEditor = this.editor;

      const tryResolveLeafImage = (index) => {
        if (!Number.isFinite(index) || index < 0) {
          return null;
        }

        const [leaf] = currentEditor.getLeaf(index);

        return leaf?.domNode?.tagName === 'IMG' ? leaf.domNode : null;
      };

      return tryResolveLeafImage(imageIndex)
        || tryResolveLeafImage(imageIndex - 1)
        || Array.from(currentEditor.root.querySelectorAll('img')).find((imageNode) => {
          if (previousSrc && imageNode.getAttribute('src') !== previousSrc) {
            return false;
          }

          const width = Number.parseFloat(imageNode.getAttribute('data-width-px')) || 0;
          const height = Number.parseFloat(imageNode.getAttribute('data-height-px'))
            || (imageNode.style.height && imageNode.style.height !== 'auto' ? Number.parseFloat(imageNode.style.height) : 0)
            || imageNode.getBoundingClientRect().height
            || 0;
          const translateX = Number.parseFloat(imageNode.getAttribute('data-x')) || 0;
          const translateY = Number.parseFloat(imageNode.getAttribute('data-y')) || 0;

          return Math.abs(width - previousWidth) <= 8
            && Math.abs(height - previousHeight) <= 8
            && Math.abs(translateX - previousX) <= 8
            && Math.abs(translateY - previousY) <= 8;
        })
        || Array.from(currentEditor.root.querySelectorAll('img')).find((imageNode) => (
          !previousSrc || imageNode.getAttribute('src') === previousSrc
        ))
        || null;
    },
    ensureSelectedImageIsLive(imageIndex = null) {
      if (this.selectedImage && document.body.contains(this.selectedImage)) {
        return true;
      }

      const preservedMetrics = this.selectedImage
        ? this.readSelectedImageMetrics(this.selectedImage)
        : null;

      const liveImage = this.resolveLiveImageElement(
        imageIndex,
        this.selectedImage,
        preservedMetrics,
      );

      if (!liveImage) {
        return false;
      }

      this.setSelectedImage(liveImage);

      if (preservedMetrics) {
        this.syncImageElementMetrics(liveImage, preservedMetrics);
        this.updateSelectedImageFrame();
      }

      return true;
    },
    rebindSelectedImage(imageIndex, previousImage = this.selectedImage, forcedMetrics = null) {
      if (!this.editor || !previousImage) {
        this.clearSelectedImage();
        return;
      }

      const resolvedMetrics = forcedMetrics || this.readSelectedImageMetrics(previousImage);
      const previousWidth = resolvedMetrics.width || 0;
      const previousHeight = resolvedMetrics.height || 0;
      const previousX = resolvedMetrics.translateX || 0;
      const previousY = resolvedMetrics.translateY || 0;

      this.$nextTick(() => {
        window.requestAnimationFrame(() => {
        if (!this.editor?.root) {
          this.clearSelectedImage();
          return;
        }

        const liveImage = this.resolveLiveImageElement(imageIndex, previousImage, resolvedMetrics);

        if (liveImage) {
          this.setSelectedImage(liveImage);

          const liveMetrics = this.readSelectedImageMetrics(liveImage);
          const shouldSyncMetrics = Math.abs(liveMetrics.width - previousWidth) > 0.5
            || Math.abs(liveMetrics.height - previousHeight) > 0.5
            || Math.abs(liveMetrics.translateX - previousX) > 0.5
            || Math.abs(liveMetrics.translateY - previousY) > 0.5;

          if (shouldSyncMetrics) {
            this.syncImageElementMetrics(liveImage, {
              width: previousWidth || liveMetrics.width,
              height: previousHeight || liveMetrics.height,
              translateX: previousX,
              translateY: previousY,
            });
            this.updateSelectedImageFrame();
          }

          return;
        }

        this.clearSelectedImage();
        });
      });
    },
    readSelectedImageMetrics(imageElement = this.selectedImage) {
      if (!imageElement) {
        return {
          width: 0,
          height: 0,
          translateX: 0,
          translateY: 0,
        };
      }

      return {
        width: Number.parseFloat(imageElement.getAttribute('data-width-px')) || imageElement.getBoundingClientRect().width || 0,
        height: Number.parseFloat(imageElement.getAttribute('data-height-px'))
          || (imageElement.style.height && imageElement.style.height !== 'auto' ? Number.parseFloat(imageElement.style.height) : 0)
          || imageElement.getBoundingClientRect().height
          || 0,
        translateX: Number.parseFloat(imageElement.getAttribute('data-x')) || 0,
        translateY: Number.parseFloat(imageElement.getAttribute('data-y')) || 0,
      };
    },
    normalizeImageMetrics(metrics = {}) {
      const currentMetrics = this.readSelectedImageMetrics();
      const editorWidth = this.editor?.root?.clientWidth || 0;
      const maxWidth = Math.max(MIN_IMAGE_DIMENSION, Math.round((editorWidth || metrics.width || currentMetrics.width || MIN_IMAGE_DIMENSION) - 8));
      let nextWidth = Math.max(
        MIN_IMAGE_DIMENSION,
        Math.round(Number.isFinite(metrics.width) ? metrics.width : currentMetrics.width),
      );
      let nextHeight = Math.max(
        MIN_IMAGE_DIMENSION,
        Math.round(Number.isFinite(metrics.height) ? metrics.height : currentMetrics.height),
      );

      if (nextWidth > maxWidth) {
        const scale = maxWidth / nextWidth;
        nextWidth = maxWidth;
        nextHeight = Math.max(MIN_IMAGE_DIMENSION, Math.round(nextHeight * scale));
      }

      const maxTranslateX = Math.max(0, (editorWidth || nextWidth) - nextWidth);
      const rawTranslateX = Math.round(
        Number.isFinite(metrics.translateX) ? metrics.translateX : currentMetrics.translateX,
      );
      const rawTranslateY = Math.round(
        Number.isFinite(metrics.translateY) ? metrics.translateY : currentMetrics.translateY,
      );

      return {
        width: nextWidth,
        height: nextHeight,
        translateX: Math.max(0, Math.min(rawTranslateX, maxTranslateX)),
        translateY: Math.max(0, rawTranslateY),
      };
    },
    updateSelectedImageFrame() {
      if (!this.selectedImage || !this.$refs.surface) {
        this.selectedImageFrame = null;
        return;
      }

      if (!document.body.contains(this.selectedImage) && !this.ensureSelectedImageIsLive(this.imageInteraction?.imageIndex)) {
        this.selectedImageFrame = null;
        return;
      }

      const imageRect = this.selectedImage.getBoundingClientRect();
      const surfaceRect = this.$refs.surface.getBoundingClientRect();

      this.selectedImageFrame = {
        top: imageRect.top - surfaceRect.top,
        left: imageRect.left - surfaceRect.left,
        width: imageRect.width,
        height: imageRect.height,
      };
    },
    applyImageMetrics({ width, height, translateX, translateY }, options = {}) {
      if (!this.selectedImage) {
        return;
      }

      const {
        syncEditorModel = true,
        emitValue = syncEditorModel,
      } = options;

      const normalizedMetrics = this.normalizeImageMetrics({
        width,
        height,
        translateX,
        translateY,
      });

      this.syncImageElementMetrics(this.selectedImage, {
        width: normalizedMetrics.width,
        height: normalizedMetrics.height,
        translateX: normalizedMetrics.translateX,
        translateY: normalizedMetrics.translateY,
      });

      if (syncEditorModel) {
        this.persistImageMetrics(this.selectedImage);
      }

      this.updateSelectedImageFrame();

      if (emitValue) {
        this.emitEditorValue();
      }
    },
    captureImagePointer(pointerTarget, pointerId) {
      if (!pointerTarget || typeof pointerTarget.setPointerCapture !== 'function' || !Number.isInteger(pointerId)) {
        return;
      }

      try {
        pointerTarget.setPointerCapture(pointerId);
      } catch (error) {
        // Ignore synthetic or unsupported pointer capture failures.
      }
    },
    releaseImagePointer(pointerTarget, pointerId) {
      if (!pointerTarget || typeof pointerTarget.releasePointerCapture !== 'function' || !Number.isInteger(pointerId)) {
        return;
      }

      try {
        pointerTarget.releasePointerCapture(pointerId);
      } catch (error) {
        // Ignore synthetic or already-released pointer capture failures.
      }
    },
    startImageDrag(event) {
      if (this.lockImages || !this.selectedImage) {
        return;
      }

      const moveListener = this.imageInteractionMoveListener || ((moveEvent) => this.handleImageInteractionMove(moveEvent));
      const stopListener = this.imageInteractionStopListener || (() => this.stopImageInteraction());
      const pointerTarget = event.currentTarget || event.target || null;
      const imageBlot = this.editor ? Quill.find(this.selectedImage, true) : null;
      const imageIndex = imageBlot && this.editor ? this.editor.getIndex(imageBlot) : -1;
      this.captureImagePointer(pointerTarget, event.pointerId);

      const { width, height, translateX, translateY } = this.readSelectedImageMetrics();

      this.imageInteraction = {
        mode: 'move',
        startPointerX: event.clientX,
        startPointerY: event.clientY,
        startWidth: width,
        startHeight: height,
        startTranslateX: translateX,
        startTranslateY: translateY,
        handle: '',
        imageIndex,
        pointerId: Number.isInteger(event.pointerId) ? event.pointerId : null,
        pointerTarget,
      };
      this.pendingImageInteractionPoint = null;

      window.addEventListener('pointermove', moveListener);
      window.addEventListener('pointerup', stopListener);
      window.addEventListener('pointercancel', stopListener);
    },
    startImageResize(handle, event) {
      if (this.lockImages || !this.selectedImage) {
        return;
      }

      const moveListener = this.imageInteractionMoveListener || ((moveEvent) => this.handleImageInteractionMove(moveEvent));
      const stopListener = this.imageInteractionStopListener || (() => this.stopImageInteraction());
      const pointerTarget = event.currentTarget || event.target || null;
      const imageBlot = this.editor ? Quill.find(this.selectedImage, true) : null;
      const imageIndex = imageBlot && this.editor ? this.editor.getIndex(imageBlot) : -1;
      this.captureImagePointer(pointerTarget, event.pointerId);

      const { width, height, translateX, translateY } = this.readSelectedImageMetrics();
      const safeHeight = Math.max(height || 0, 1);

      this.imageInteraction = {
        mode: 'resize',
        startPointerX: event.clientX,
        startPointerY: event.clientY,
        startWidth: width,
        startHeight: safeHeight,
        startTranslateX: translateX,
        startTranslateY: translateY,
        aspectRatio: width > 0 ? width / safeHeight : 1,
        handle,
        imageIndex,
        pointerId: Number.isInteger(event.pointerId) ? event.pointerId : null,
        pointerTarget,
      };
      this.pendingImageInteractionPoint = null;

      window.addEventListener('pointermove', moveListener);
      window.addEventListener('pointerup', stopListener);
      window.addEventListener('pointercancel', stopListener);
    },
    applyPendingImageInteraction() {
      if (!this.selectedImage || !this.imageInteraction) {
        return;
      }

      if (!this.ensureSelectedImageIsLive(this.imageInteraction.imageIndex)) {
        return;
      }

      const point = this.pendingImageInteractionPoint;

      if (!point) {
        return;
      }

      const deltaX = point.clientX - this.imageInteraction.startPointerX;
      const deltaY = point.clientY - this.imageInteraction.startPointerY;

      if (this.imageInteraction.mode === 'move') {
        this.applyImageMetrics({
          width: this.imageInteraction.startWidth,
          height: this.imageInteraction.startHeight,
          translateX: this.imageInteraction.startTranslateX + deltaX,
          translateY: this.imageInteraction.startTranslateY + deltaY,
        }, { syncEditorModel: false, emitValue: false });
        this.pendingImageInteractionPoint = null;
        return;
      }

      const handle = this.imageInteraction.handle || 'se';
      const widthDelta = handle.includes('w') ? -deltaX : deltaX;
      const heightDelta = handle.includes('n') ? -deltaY : deltaY;
      const startWidth = Math.max(this.imageInteraction.startWidth || 0, MIN_IMAGE_DIMENSION);
      const startHeight = Math.max(this.imageInteraction.startHeight || 0, 1);
      const aspectRatio = this.imageInteraction.aspectRatio || (startWidth / startHeight) || 1;
      const widthScale = (startWidth + widthDelta) / startWidth;
      const heightScale = (startHeight + heightDelta) / startHeight;
      const preferredScale = Math.abs(widthScale - 1) >= Math.abs(heightScale - 1)
        ? widthScale
        : heightScale;
      const minimumScale = Math.max(
        MIN_IMAGE_DIMENSION / startWidth,
        MIN_IMAGE_DIMENSION / startHeight,
      );
      const nextScale = Math.max(minimumScale, preferredScale);
      const nextWidth = startWidth * nextScale;
      const nextHeight = nextWidth / aspectRatio;
      const nextTranslateX = handle.includes('w')
        ? this.imageInteraction.startTranslateX + (startWidth - nextWidth)
        : this.imageInteraction.startTranslateX;
      const nextTranslateY = handle.includes('n')
        ? this.imageInteraction.startTranslateY + (startHeight - nextHeight)
        : this.imageInteraction.startTranslateY;

      this.applyImageMetrics({
        width: nextWidth,
        height: nextHeight,
        translateX: nextTranslateX,
        translateY: nextTranslateY,
      }, { syncEditorModel: false, emitValue: false });
      this.pendingImageInteractionPoint = null;
    },
    scheduleImageInteractionUpdate() {
      if (this.imageInteractionFrameRequest) {
        return;
      }

      this.imageInteractionFrameRequest = window.requestAnimationFrame(() => {
        this.imageInteractionFrameRequest = null;
        this.applyPendingImageInteraction();
      });
    },
    handleImageInteractionMove(event) {
      if (!this.selectedImage || !this.imageInteraction) {
        return;
      }

      this.pendingImageInteractionPoint = {
        clientX: event.clientX,
        clientY: event.clientY,
      };
      this.applyPendingImageInteraction();
    },
    stopImageInteraction() {
      const moveListener = this.imageInteractionMoveListener || this.handleImageInteractionMove;
      const stopListener = this.imageInteractionStopListener || this.stopImageInteraction;

      window.removeEventListener('pointermove', moveListener);
      window.removeEventListener('pointerup', stopListener);
      window.removeEventListener('pointercancel', stopListener);
      this.releaseImagePointer(this.imageInteraction?.pointerTarget, this.imageInteraction?.pointerId);

      if (this.imageInteractionFrameRequest) {
        window.cancelAnimationFrame(this.imageInteractionFrameRequest);
        this.imageInteractionFrameRequest = null;
      }

      this.applyPendingImageInteraction();

      if (this.selectedImage) {
        const selectedImage = this.selectedImage;
        const finalMetrics = this.readSelectedImageMetrics(selectedImage);
        const imageBlot = this.editor ? Quill.find(this.selectedImage, true) : null;
        const imageIndex = imageBlot && this.editor
          ? this.editor.getIndex(imageBlot)
          : (this.imageInteraction?.imageIndex ?? -1);
        const liveImage = this.resolveLiveImageElement(imageIndex, selectedImage, finalMetrics);

        if (liveImage) {
          this.syncImageElementMetrics(liveImage, finalMetrics);
        }

        this.persistImageMetrics(selectedImage);
        window.requestAnimationFrame(() => {
          window.requestAnimationFrame(() => {
            if (!this.editor) {
              return;
            }

            this.emitEditorValue();
            this.rebindSelectedImage(imageIndex, selectedImage, finalMetrics);
          });
        });
      }

      this.pendingImageInteractionPoint = null;
      this.imageInteraction = null;
    },
    emitEditorValue() {
      if (!this.editor) {
        return;
      }

      this.$emit('input', this.editor.root.innerHTML);
    },
    destroyEditor() {
      if (!this.editor) {
        return;
      }

      this.editor.root.removeEventListener('click', this.handleEditorClick);
      this.editor.root.removeEventListener('pointerdown', this.handleEditorPointerDown);
      this.editor.root.removeEventListener('paste', this.handleEditorPaste);
      this.editor.root.removeEventListener('contextmenu', this.handleTableContextMenu);
      this.$refs.toolbar?.removeEventListener('pointerdown', this.toolbarPointerDownListener, true);
      this.editor.off('text-change', this.handleEditorTextChange);
      this.editor.off('selection-change', this.handleSelectionChange);
      window.removeEventListener('resize', this.handleViewportChange);
      window.removeEventListener('scroll', this.handleViewportChange, true);
      window.removeEventListener('pointerdown', this.handleWindowPointerDown, true);
      this.stopImageInteraction();
      this.hideTableContextMenu();
      this.clearSelectedImage();
      this.imageInteractionMoveListener = null;
      this.imageInteractionStopListener = null;
      this.editor = null;
    },
  },
};
</script>

<style scoped>
.rich-text-editor {
  width: 100%;
  max-width: 100%;
  min-width: 0;
  overflow: hidden;
}

.rich-text-editor--disabled {
  opacity: 0.92;
}

.rich-text-editor__image-input {
  display: none;
}

.rich-text-editor__toolbar :deep(.ql-toolbar.ql-snow),
.rich-text-editor__toolbar {
  display: flex;
  flex-wrap: wrap;
  gap: 8px;
  align-items: center;
  justify-content: flex-end;
  padding: 12px;
  border: 1px solid rgba(148, 163, 184, 0.22);
  border-radius: 18px 18px 0 0;
  background: #f8fafc;
}

.rich-text-editor__toolbar :deep(.ql-formats) {
  display: inline-flex;
  align-items: center;
  justify-content: flex-end;
  flex-wrap: wrap;
  gap: 6px;
  margin: 0;
  min-width: 0;
  max-width: 100%;
  padding: 0;
}

.rich-text-editor__toolbar :deep(.ql-formats button:not(.ql-table)) {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  width: 28px;
  height: 28px;
  min-width: 28px;
  padding: 0;
  border-radius: 8px;
}

.rich-text-editor__toolbar :deep(.ql-picker-label svg) {
  display: none;
}

.rich-text-editor__toolbar :deep(select.ql-font) {
  display: none !important;
}

.rich-text-editor__toolbar :deep(select.ql-table) {
  display: none !important;
}

.rich-text-editor__toolbar :deep(button.ql-table.rich-text-editor__table-trigger) {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  width: 24px;
  height: 24px;
  padding: 0;
  border: 0;
  background: transparent;
  box-shadow: none;
  color: #0f172a;
}

.rich-text-editor__toolbar :deep(button.ql-table.rich-text-editor__table-trigger:hover),
.rich-text-editor__toolbar :deep(button.ql-table.rich-text-editor__table-trigger:focus-visible) {
  color: #0f172a;
  background: rgba(15, 23, 42, 0.06);
}

.rich-text-editor__toolbar :deep(button.ql-undo svg) {
  width: 14px;
  height: 14px;
}

.rich-text-editor__toolbar :deep(button.ql-undo .ql-stroke) {
  stroke: #444;
}

.rich-text-editor__toolbar :deep(button.ql-undo:hover .ql-stroke),
.rich-text-editor__toolbar :deep(button.ql-undo.ql-active .ql-stroke) {
  stroke: #06c;
}

.rich-text-editor__table-trigger-icon {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  color: currentColor;
  font-size: 0.88rem;
  font-weight: 800;
  line-height: 1;
}

.rich-text-editor__toolbar :deep(.ql-picker.ql-font) {
  display: flex;
  align-items: stretch;
  min-width: 170px;
  height: auto;
  padding: 0 !important;
  border: 0 !important;
  background: transparent !important;
  box-shadow: none !important;
  direction: rtl;
  text-align: right;
  float: none;
}

.rich-text-editor__toolbar :deep(.ql-picker.ql-table) {
  display: flex;
  align-items: stretch;
  min-width: 168px;
  height: auto;
  padding: 0 !important;
  border: 0 !important;
  background: transparent !important;
  box-shadow: none !important;
  direction: rtl;
  text-align: right;
  float: none;
}

.rich-text-editor__toolbar :deep(.ql-picker.ql-table.rich-text-editor__table-picker--actions) {
  min-width: 182px;
}

.rich-text-editor__toolbar :deep(.ql-picker.ql-font .ql-picker-label),
.rich-text-editor__toolbar :deep(.ql-picker.ql-font .ql-picker-item) {
  display: flex;
  align-items: center;
  min-height: 54px;
  font-size: 0.98rem;
  font-weight: 700;
  line-height: 1.2;
}

.rich-text-editor__toolbar :deep(.ql-picker.ql-table .ql-picker-label),
.rich-text-editor__toolbar :deep(.ql-picker.ql-table .ql-picker-item) {
  display: flex;
  align-items: center;
  min-height: 54px;
  font-size: 0.95rem;
  font-weight: 700;
  line-height: 1.2;
}

.rich-text-editor__toolbar :deep(.ql-picker.ql-font .ql-picker-label) {
  position: relative;
  justify-content: flex-start;
  width: 100%;
  padding: 0 18px !important;
  padding-left: 46px !important;
  border: 1px solid var(--app-select-border, rgba(143, 191, 211, 0.72));
  border-radius: var(--app-select-radius, 18px);
  background: var(--app-select-bg, #ffffff);
  box-shadow: var(--app-select-shadow, 0 12px 28px rgba(15, 23, 42, 0.05), inset 0 1px 0 rgba(255, 255, 255, 0.94));
  color: var(--app-select-text, #123f56);
  cursor: pointer;
  transition: border-color 0.18s ease, box-shadow 0.18s ease, background-color 0.18s ease;
}

.rich-text-editor__toolbar :deep(.ql-picker.ql-table .ql-picker-label) {
  position: relative;
  justify-content: flex-start;
  width: 100%;
  padding: 0 18px !important;
  padding-left: 46px !important;
  border: 1px solid var(--app-select-border, rgba(143, 191, 211, 0.72));
  border-radius: var(--app-select-radius, 18px);
  background: var(--app-select-bg, #ffffff);
  box-shadow: var(--app-select-shadow, 0 12px 28px rgba(15, 23, 42, 0.05), inset 0 1px 0 rgba(255, 255, 255, 0.94));
  color: var(--app-select-text, #123f56);
  cursor: pointer;
  transition: border-color 0.18s ease, box-shadow 0.18s ease, background-color 0.18s ease;
}

.rich-text-editor__toolbar :deep(.ql-picker.ql-font .ql-picker-label:hover),
.rich-text-editor__toolbar :deep(.ql-picker.ql-font.ql-expanded .ql-picker-label) {
  border-color: var(--app-select-border-strong, #107699);
  box-shadow: var(--app-select-focus-shadow, 0 0 0 4px rgba(16, 118, 153, 0.14));
}

.rich-text-editor__toolbar :deep(.ql-picker.ql-table .ql-picker-label:hover),
.rich-text-editor__toolbar :deep(.ql-picker.ql-table.ql-expanded .ql-picker-label) {
  border-color: var(--app-select-border-strong, #107699);
  box-shadow: var(--app-select-focus-shadow, 0 0 0 4px rgba(16, 118, 153, 0.14));
}

.rich-text-editor__toolbar :deep(.ql-picker.ql-font .ql-picker-label::after) {
  content: '';
  position: absolute;
  top: 50%;
  left: 18px;
  width: 9px;
  height: 9px;
  border-right: 2px solid var(--app-select-border-strong, #107699);
  border-bottom: 2px solid var(--app-select-border-strong, #107699);
  transform: translateY(-65%) rotate(45deg);
  pointer-events: none;
}

.rich-text-editor__toolbar :deep(.ql-picker.ql-table .ql-picker-label::after) {
  content: '';
  position: absolute;
  top: 50%;
  left: 18px;
  width: 9px;
  height: 9px;
  border-right: 2px solid var(--app-select-border-strong, #107699);
  border-bottom: 2px solid var(--app-select-border-strong, #107699);
  transform: translateY(-65%) rotate(45deg);
  pointer-events: none;
}

.rich-text-editor__toolbar :deep(.ql-picker.ql-font.ql-expanded .ql-picker-label::after) {
  transform: translateY(-35%) rotate(-135deg);
}

.rich-text-editor__toolbar :deep(.ql-picker.ql-table.ql-expanded .ql-picker-label::after) {
  transform: translateY(-35%) rotate(-135deg);
}

.rich-text-editor__toolbar :deep(.ql-picker.ql-font .ql-picker-options) {
  right: 0;
  left: auto !important;
  min-width: 100%;
  margin-top: 8px;
  padding: 8px 0;
  border: 1px solid rgba(189, 219, 231, 0.92);
  border-radius: 18px;
  background: #ffffff;
  box-shadow: 0 24px 52px rgba(15, 23, 42, 0.12);
  overflow: hidden;
}

.rich-text-editor__toolbar :deep(.ql-picker.ql-table .ql-picker-options) {
  right: 0;
  left: auto !important;
  min-width: 100%;
  margin-top: 8px;
  padding: 8px 0;
  border: 1px solid rgba(189, 219, 231, 0.92);
  border-radius: 18px;
  background: #ffffff;
  box-shadow: 0 24px 52px rgba(15, 23, 42, 0.12);
  overflow: hidden;
}

.rich-text-editor__toolbar :deep(.ql-picker.ql-font .ql-picker-item) {
  justify-content: flex-start;
  min-height: 52px;
  padding: 0 18px !important;
  color: #123f56;
  cursor: pointer;
  transition: background-color 0.18s ease, color 0.18s ease;
}

.rich-text-editor__toolbar :deep(.ql-picker.ql-table .ql-picker-item) {
  justify-content: flex-start;
  min-height: 50px;
  padding: 0 18px !important;
  color: #123f56;
  cursor: pointer;
  transition: background-color 0.18s ease, color 0.18s ease;
}

.rich-text-editor__toolbar :deep(.ql-picker.ql-font .ql-picker-item:hover),
.rich-text-editor__toolbar :deep(.ql-picker.ql-font .ql-picker-item.ql-selected) {
  background: rgba(31, 111, 150, 0.1);
  color: #1f6f96;
}

.rich-text-editor__toolbar :deep(.ql-picker.ql-table .ql-picker-item:hover),
.rich-text-editor__toolbar :deep(.ql-picker.ql-table .ql-picker-item.ql-selected) {
  background: rgba(31, 111, 150, 0.1);
  color: #1f6f96;
}

.rich-text-editor__toolbar :deep(.ql-picker.ql-font .ql-picker-label::before),
.rich-text-editor__toolbar :deep(.ql-picker.ql-font .ql-picker-item::before) {
  display: inline-flex;
  align-items: center;
  color: inherit;
  font-size: 0.98rem;
  font-weight: 700;
  line-height: 1.2;
}

.rich-text-editor__toolbar :deep(.ql-picker.ql-table .ql-picker-label[data-label]::before),
.rich-text-editor__toolbar :deep(.ql-picker.ql-table .ql-picker-item[data-label]::before) {
  content: attr(data-label);
}

.rich-text-editor__toolbar :deep(.ql-picker.ql-font .ql-picker-label[data-value='traditional-arabic']::before),
.rich-text-editor__toolbar :deep(.ql-picker.ql-font .ql-picker-item[data-value='traditional-arabic']::before) {
  content: 'العربي التقليدي';
  font-family: 'Traditional Arabic', Tahoma, serif;
}

.rich-text-editor__toolbar :deep(.ql-picker.ql-font .ql-picker-label[data-value='simplified-arabic']::before),
.rich-text-editor__toolbar :deep(.ql-picker.ql-font .ql-picker-item[data-value='simplified-arabic']::before) {
  content: 'العربي المبسط';
  font-family: 'Simplified Arabic', Tahoma, sans-serif;
}

.rich-text-editor__toolbar :deep(.ql-picker.ql-font .ql-picker-label[data-value='arabic-typesetting']::before),
.rich-text-editor__toolbar :deep(.ql-picker.ql-font .ql-picker-item[data-value='arabic-typesetting']::before) {
  content: 'صف الحروف العربي';
  font-family: 'Arabic Typesetting', 'Traditional Arabic', Tahoma, serif;
}

.rich-text-editor__toolbar :deep(.ql-picker.ql-font .ql-picker-label[data-value='tahoma']::before),
.rich-text-editor__toolbar :deep(.ql-picker.ql-font .ql-picker-item[data-value='tahoma']::before) {
  content: 'تاهوما';
  font-family: Tahoma, Arial, sans-serif;
}

.rich-text-editor__toolbar :deep(.ql-picker.ql-font .ql-picker-label[data-value='arial']::before),
.rich-text-editor__toolbar :deep(.ql-picker.ql-font .ql-picker-item[data-value='arial']::before) {
  content: 'آريال';
  font-family: Arial, Helvetica, sans-serif;
}

.rich-text-editor__toolbar :deep(.ql-picker.ql-font .ql-picker-label[data-value='segoe-ui']::before),
.rich-text-editor__toolbar :deep(.ql-picker.ql-font .ql-picker-item[data-value='segoe-ui']::before) {
  content: 'سيغو UI';
  font-family: 'Segoe UI', Tahoma, sans-serif;
}

.rich-text-editor__toolbar :deep(.ql-picker.ql-font .ql-picker-label:not([data-value])::before),
.rich-text-editor__toolbar :deep(.ql-picker.ql-font .ql-picker-item:not([data-value])::before) {
  content: 'الخط';
}

.rich-text-editor__surface {
  position: relative;
  padding: 20px 14px 28px;
  border: 1px solid rgba(148, 163, 184, 0.22);
  border-top: 0;
  border-radius: 0 0 22px 22px;
  background: linear-gradient(180deg, #eef3f7 0%, #e9eff4 100%);
  overflow-x: auto;
}

.rich-text-editor__surface--dragging {
  background: linear-gradient(180deg, #dff4fb 0%, #e7f6fb 100%);
}

.rich-text-editor__table-menu {
  position: absolute;
  z-index: 6;
  display: grid;
  gap: 6px;
  min-width: 220px;
  padding: 10px;
  border: 1px solid rgba(148, 163, 184, 0.24);
  border-radius: 18px;
  background: rgba(255, 255, 255, 0.98);
  box-shadow: 0 20px 44px rgba(15, 23, 42, 0.16);
  backdrop-filter: blur(12px);
}

.rich-text-editor__table-menu-item {
  width: 100%;
  padding: 10px 12px;
  border: 0;
  border-radius: 12px;
  background: transparent;
  color: #0f172a;
  font-size: 0.95rem;
  font-weight: 700;
  line-height: 1.4;
  text-align: right;
  cursor: pointer;
  transition: background-color 0.18s ease, color 0.18s ease;
}

.rich-text-editor__table-menu-item:hover,
.rich-text-editor__table-menu-item:focus-visible {
  background: rgba(15, 23, 42, 0.06);
  outline: none;
}

.rich-text-editor__table-menu-item--danger {
  color: #b42318;
}

.rich-text-editor__table-menu-item--danger:hover,
.rich-text-editor__table-menu-item--danger:focus-visible {
  background: rgba(180, 35, 24, 0.1);
}

.rich-text-editor__image-frame {
  position: absolute;
  z-index: 3;
  border: 2px solid rgba(14, 116, 144, 0.34);
  border-radius: 12px;
  pointer-events: auto;
  cursor: move;
  touch-action: none;
  box-shadow: 0 0 0 4px rgba(14, 116, 144, 0.08);
}

.rich-text-editor__image-handle {
  position: absolute;
  width: 14px;
  height: 14px;
  border: 2px solid #ffffff;
  border-radius: 999px;
  background: #0e7490;
  box-shadow: 0 4px 10px rgba(15, 23, 42, 0.18);
  pointer-events: auto;
  touch-action: none;
}

.rich-text-editor__image-handle--nw {
  top: -8px;
  left: -8px;
  cursor: nwse-resize;
}

.rich-text-editor__image-handle--ne {
  top: -8px;
  right: -8px;
  cursor: nesw-resize;
}

.rich-text-editor__image-handle--sw {
  bottom: -8px;
  left: -8px;
  cursor: nesw-resize;
}

.rich-text-editor__image-handle--se {
  right: -8px;
  bottom: -8px;
  cursor: nwse-resize;
}

.rich-text-editor__drop-hint {
  position: sticky;
  top: 8px;
  z-index: 2;
  width: fit-content;
  margin: 0 auto 12px;
  padding: 10px 16px;
  border-radius: 999px;
  background: rgba(14, 116, 144, 0.92);
  color: #ffffff;
  font-size: 0.92rem;
  font-weight: 700;
}

.rich-text-editor__editor :deep(.ql-container.ql-snow) {
  border: 0;
  font-size: 0.96rem;
  width: 100%;
  max-width: 100%;
  min-width: 0;
  height: auto;
  overflow: visible;
  box-sizing: border-box;
}

.rich-text-editor__editor :deep(.ql-editor) {
  position: relative;
  width: min(100%, 210mm);
  max-width: 100%;
  min-height: max(var(--rich-text-editor-min-height), 297mm);
  margin: 0 auto;
  padding: 25.4mm;
  background: #fff;
  color: #0f172a;
  box-shadow: 0 24px 50px rgba(15, 23, 42, 0.12);
  border: 1px solid rgba(148, 163, 184, 0.16);
  direction: rtl;
  text-align: right;
  line-height: 1.9;
  overflow: visible;
  box-sizing: border-box;
}

.rich-text-editor__editor :deep(.ql-editor.ql-blank::before) {
  right: 25.4mm;
  left: auto;
  color: rgba(15, 23, 42, 0.38);
  font-style: normal;
}

.rich-text-editor__editor :deep(.ql-editor p),
.rich-text-editor__editor :deep(.ql-editor h1),
.rich-text-editor__editor :deep(.ql-editor h2),
.rich-text-editor__editor :deep(.ql-editor h3),
.rich-text-editor__editor :deep(.ql-editor li),
.rich-text-editor__editor :deep(.ql-editor blockquote) {
  text-align: right;
  overflow-wrap: anywhere;
}

.rich-text-editor__editor :deep(.ql-editor table) {
  width: 100% !important;
  max-width: 100% !important;
  table-layout: fixed;
}

.rich-text-editor__editor :deep(.ql-editor td),
.rich-text-editor__editor :deep(.ql-editor th) {
  word-break: break-word;
  overflow-wrap: anywhere;
}

.rich-text-editor__editor :deep(.ql-editor img) {
  display: block;
  position: absolute;
  max-width: none;
  margin: 0;
  border-radius: 10px;
  cursor: grab;
  user-select: none;
  touch-action: none;
  transform-origin: center center;
}

.rich-text-editor__editor :deep(.ql-font-tahoma) {
  font-family: Tahoma, Arial, sans-serif;
}

.rich-text-editor__editor :deep(.ql-font-arial) {
  font-family: Arial, Helvetica, sans-serif;
}

.rich-text-editor__editor :deep(.ql-font-segoe-ui) {
  font-family: 'Segoe UI', Tahoma, sans-serif;
}

.rich-text-editor__editor :deep(.ql-font-traditional-arabic) {
  font-family: 'Traditional Arabic', Tahoma, serif;
}

.rich-text-editor__editor :deep(.ql-font-simplified-arabic) {
  font-family: 'Simplified Arabic', Tahoma, sans-serif;
}

.rich-text-editor__editor :deep(.ql-font-arabic-typesetting) {
  font-family: 'Arabic Typesetting', 'Traditional Arabic', Tahoma, serif;
}

.rich-text-editor__editor :deep(.ql-editor img.ql-rich-image--selected) {
  cursor: grabbing;
}

@media (max-width: 900px) {
  .rich-text-editor__toolbar :deep(.ql-toolbar.ql-snow),
  .rich-text-editor__toolbar {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    align-items: stretch;
    gap: 10px;
    padding: 10px;
  }

  .rich-text-editor__toolbar :deep(.ql-formats) {
    display: flex;
    width: 100%;
    min-width: 0;
    justify-content: flex-start;
    padding: 4px;
    border: 1px solid rgba(191, 219, 230, 0.7);
    border-radius: 14px;
    background: rgba(255, 255, 255, 0.9);
  }

  .rich-text-editor__toolbar :deep(.ql-formats:first-child) {
    grid-column: 1 / -1;
    padding: 0;
    border: 0;
    background: transparent;
  }

  .rich-text-editor__toolbar :deep(.ql-picker.ql-font),
  .rich-text-editor__toolbar :deep(.ql-picker.ql-table) {
    min-width: 0;
    width: 100%;
  }

  .rich-text-editor__surface {
    padding: 8px 8px 18px;
  }

  .rich-text-editor__editor :deep(.ql-editor) {
    width: min(100%, 680px);
    min-height: max(var(--rich-text-editor-min-height), calc((min(100vw - 48px, 680px)) * 1.4142));
    padding: 24px 18px;
    box-shadow: 0 16px 32px rgba(15, 23, 42, 0.1);
    aspect-ratio: 210 / 297;
    box-sizing: border-box;
  }

  .rich-text-editor__editor :deep(.ql-editor.ql-blank::before) {
    right: 18px;
  }
}

@media (max-width: 640px) {
  .rich-text-editor__toolbar :deep(.ql-toolbar.ql-snow),
  .rich-text-editor__toolbar {
    display: grid;
    grid-template-columns: repeat(7, minmax(0, 1fr));
    gap: 6px;
    padding: 8px;
    align-items: stretch;
    justify-content: stretch;
  }

  .rich-text-editor__toolbar :deep(.ql-formats) {
    display: contents;
  }

  .rich-text-editor__toolbar :deep(.ql-formats button:not(.ql-table)) {
    width: 100%;
    min-width: 0;
    height: 30px;
    border-radius: 6px;
  }

  .rich-text-editor__toolbar :deep(button.ql-table.rich-text-editor__table-trigger) {
    width: 100%;
    min-width: 0;
    height: 30px;
    border-radius: 6px;
    background: rgba(15, 23, 42, 0.04);
  }

  .rich-text-editor__toolbar :deep(.ql-picker.ql-font) {
    grid-column: span 2;
    min-width: 0;
    width: 100%;
  }

  .rich-text-editor__toolbar :deep(.ql-toolbar button svg),
  .rich-text-editor__toolbar :deep(.ql-toolbar button .ql-stroke),
  .rich-text-editor__toolbar :deep(.ql-toolbar button .ql-fill) {
    transform: scale(0.72);
    transform-origin: center;
  }

  .rich-text-editor__toolbar :deep(.ql-picker.ql-font .ql-picker-label),
  .rich-text-editor__toolbar :deep(.ql-picker.ql-table .ql-picker-label) {
    min-height: 30px;
    padding-right: 8px !important;
    padding-left: 22px !important;
    border-radius: 8px;
    font-size: 0.64rem;
  }

  .rich-text-editor__toolbar :deep(.ql-picker.ql-font .ql-picker-item),
  .rich-text-editor__toolbar :deep(.ql-picker.ql-table .ql-picker-item) {
    min-height: 30px;
    padding-right: 8px !important;
    padding-left: 8px !important;
    font-size: 0.66rem;
  }

  .rich-text-editor__toolbar :deep(.ql-picker.ql-font .ql-picker-label::after),
  .rich-text-editor__toolbar :deep(.ql-picker.ql-table .ql-picker-label::after) {
    left: 10px;
    width: 7px;
    height: 7px;
  }

  .rich-text-editor__surface {
    padding: 4px 2px 12px;
    overflow-x: hidden;
    overflow-y: visible;
    touch-action: pan-y pinch-zoom;
  }

  .rich-text-editor__editor {
    width: 100%;
    max-width: 100%;
    min-width: 0;
    overflow-x: hidden;
    overflow-y: visible;
    touch-action: pan-y pinch-zoom;
  }

  .rich-text-editor__editor :deep(.ql-container.ql-snow) {
    height: auto;
    overflow: visible;
    touch-action: pan-y pinch-zoom;
  }

  .rich-text-editor__editor :deep(.ql-editor) {
    width: 100%;
    min-height: max(var(--rich-text-editor-min-height), calc((100vw - 56px) * 1.4142));
    max-width: 100%;
    padding: 12px 8px;
    font-size: 0.88rem;
    box-sizing: border-box;
    overflow-x: hidden;
    overflow-y: visible;
    touch-action: pan-y pinch-zoom;
    -webkit-overflow-scrolling: touch;
  }

  .rich-text-editor__editor :deep(.ql-editor.ql-blank::before) {
    right: 12px;
  }
}
</style>
