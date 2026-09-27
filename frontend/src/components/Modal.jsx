import React from 'react';
import { X, AlertTriangle } from 'lucide-react';

export default function Modal({ isOpen, onClose, onConfirm, title, content, type = 'info' }) {
  if (!isOpen) return null;

  return (
    <div className="fixed inset-0 z-50 flex items-center justify-center p-4">
      {/* Backdrop */}
      <div 
        className="absolute inset-0 bg-black/60 backdrop-blur-sm transition-opacity"
        onClick={onClose}
      ></div>

      {/* Modal Content */}
      <div className="relative w-full max-w-md bg-[#0f172a] border border-white/10 rounded-2xl shadow-2xl transform transition-all scale-100 animate-fade-in-up overflow-hidden">
        {/* Header */}
        <div className="flex justify-between items-center p-5 border-b border-white/5">
          <h3 className="text-lg font-semibold text-white flex items-center gap-2">
            {type === 'danger' && <AlertTriangle className="text-red-500 w-5 h-5" />}
            {title}
          </h3>
          <button onClick={onClose} className="text-white/40 hover:text-white transition">
            <X size={20} />
          </button>
        </div>

        {/* Body */}
        <div className="p-6">
          <p className="text-white/70 leading-relaxed">
            {content}
          </p>
        </div>

        {/* Footer */}
        <div className="flex gap-3 justify-end p-5 bg-white/5">
          <button 
            onClick={onClose}
            className="px-4 py-2 rounded-lg text-sm font-medium text-white/60 hover:text-white hover:bg-white/5 transition"
          >
            取消
          </button>
          <button 
            onClick={() => { onConfirm(); onClose(); }}
            className={`px-4 py-2 rounded-lg text-sm font-medium text-white shadow-lg transform active:scale-95 transition ${
              type === 'danger' 
                ? 'bg-red-500 hover:bg-red-600 shadow-red-500/20' 
                : 'bg-sky-500 hover:bg-sky-600 shadow-sky-500/20'
            }`}
          >
            确认
          </button>
        </div>
      </div>
    </div>
  );
}
