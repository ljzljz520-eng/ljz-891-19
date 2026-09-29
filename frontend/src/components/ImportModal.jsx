import React, { useState, useRef } from 'react';
import axios from 'axios';
import { toast } from 'react-hot-toast';
import {
  X, Upload, FileText, CheckCircle2, XCircle, AlertTriangle,
  Download, Loader2, Copy, RefreshCw
} from 'lucide-react';

const PREVIEW_ROW_LIMIT = 5;

export default function ImportModal({ isOpen, onClose, onImported }) {
  const [step, setStep] = useState('upload'); // 'upload' | 'preview' | 'result'
  const [loading, setLoading] = useState(false);
  const [fileName, setFileName] = useState('');
  const [preview, setPreview] = useState(null);
  const [dupMode, setDupMode] = useState('skip'); // 'skip' | 'overwrite'
  const [result, setResult] = useState(null);
  const fileInputRef = useRef(null);

  if (!isOpen) return null;

  const reset = () => {
    setStep('upload');
    setLoading(false);
    setFileName('');
    setPreview(null);
    setResult(null);
    setDupMode('skip');
    if (fileInputRef.current) fileInputRef.current.value = '';
  };

  const handleClose = () => {
    if (loading) return;
    reset();
    onClose();
  };

  const handleFile = async (selected) => {
    const f = selected?.[0];
    if (!f) return;
    if (!/\.csv$/i.test(f.name)) {
      toast.error('请上传 .csv 格式的文件');
      return;
    }
    setFileName(f.name);
    setLoading(true);
    const formData = new FormData();
    formData.append('file', f);
    try {
      const res = await axios.post('/api/license/import-preview', formData, {
        headers: { 'Content-Type': 'multipart/form-data' },
      });
      setPreview(res.data);
      setStep('preview');
    } catch (err) {
      toast.error('解析失败：' + (err.response?.data?.message || '请检查文件格式'));
    } finally {
      setLoading(false);
    }
  };

  const handleConfirm = async () => {
    if (!preview || preview.valid_count === 0) return;
    setLoading(true);
    try {
      const res = await axios.post('/api/license/import', {
        rows: preview.rows,
        duplicate_mode: dupMode,
      });
      setResult(res.data);
      setStep('result');
      onImported?.();
    } catch (err) {
      toast.error('导入失败：' + (err.response?.data?.message || '网络错误'));
    } finally {
      setLoading(false);
    }
  };

  const downloadTemplate = () => {
    const csv = '﻿授权QQ,授权主人,产品名称,授权上级,有效期\n123456789,张三,超级授权系统VIP版,总代理,2026-12-31 23:59:59\n987654321,李四,企业级管理后台,核心代理,2026-06-30 23:59:59\n';
    const blob = new Blob([csv], { type: 'text/csv;charset=utf-8;' });
    const url = URL.createObjectURL(blob);
    const a = document.createElement('a');
    a.href = url;
    a.download = '授权导入模板.csv';
    a.click();
    URL.revokeObjectURL(url);
  };

  const summaryCards = preview ? [
    { label: '数据总行数', value: preview.total, color: 'text-white', bg: 'bg-white/5' },
    { label: '校验通过', value: preview.valid_count, color: 'text-emerald-400', bg: 'bg-emerald-500/10' },
    { label: '缺失/错误', value: preview.invalid_count, color: 'text-red-400', bg: 'bg-red-500/10' },
    { label: '重复QQ', value: preview.duplicate_count, color: 'text-amber-400', bg: 'bg-amber-500/10' },
  ] : [];

  return (
    <div className="fixed inset-0 z-50 flex items-center justify-center p-4">
      {/* Backdrop */}
      <div className="absolute inset-0 bg-black/60 backdrop-blur-sm" onClick={handleClose} />

      {/* Modal Content */}
      <div className="relative w-full max-w-3xl bg-[#0f172a] border border-white/10 rounded-2xl shadow-2xl animate-fade-in-up overflow-hidden flex flex-col max-h-[90vh]">
        {/* Header */}
        <div className="flex justify-between items-center p-5 border-b border-white/5 shrink-0">
          <h3 className="text-lg font-semibold text-white flex items-center gap-2">
            <Upload className="text-sky-400 w-5 h-5" />
            批量导入授权（CSV）
          </h3>
          <button onClick={handleClose} className="text-white/40 hover:text-white transition">
            <X size={20} />
          </button>
        </div>

        {/* Body */}
        <div className="p-6 overflow-y-auto">
          {/* Step 1: Upload */}
          {step === 'upload' && (
            <div className="space-y-5">
              <div
                onDragOver={(e) => { e.preventDefault(); }}
                onDrop={(e) => {
                  e.preventDefault();
                  if (!loading) handleFile(e.dataTransfer.files);
                }}
                onClick={() => fileInputRef.current?.click()}
                className="border-2 border-dashed border-white/15 rounded-xl p-12 text-center cursor-pointer hover:border-sky-500/50 hover:bg-sky-500/5 transition group"
              >
                <input
                  ref={fileInputRef}
                  type="file"
                  accept=".csv,text/csv"
                  className="hidden"
                  onChange={(e) => handleFile(e.target.files)}
                />
                {loading ? (
                  <Loader2 className="w-12 h-12 mx-auto text-sky-400 animate-spin mb-4" />
                ) : (
                  <Upload className="w-12 h-12 mx-auto text-white/30 group-hover:text-sky-400 transition mb-4" />
                )}
                <p className="text-white/70 font-medium">
                  {loading ? '正在解析文件...' : '点击或拖拽 CSV 文件到此处上传'}
                </p>
                <p className="text-white/30 text-sm mt-2">
                  支持表头：授权QQ、授权主人、产品名称、授权上级、有效期
                </p>
              </div>

              <div className="flex items-center justify-between rounded-lg bg-white/5 px-4 py-3">
                <div className="flex items-center gap-2 text-sm text-white/50">
                  <FileText size={16} />
                  <span>不确定格式？请先下载标准模板</span>
                </div>
                <button
                  onClick={downloadTemplate}
                  className="flex items-center gap-1.5 text-sm text-sky-400 hover:text-sky-300 transition"
                >
                  <Download size={15} /> 下载模板
                </button>
              </div>
            </div>
          )}

          {/* Step 2: Preview */}
          {step === 'preview' && preview && (
            <div className="space-y-5">
              {/* File info */}
              <div className="flex items-center gap-2 text-sm text-white/50">
                <FileText size={15} className="text-sky-400" />
                <span className="text-white/80">{fileName || preview.file_name}</span>
                <span className="text-white/30">·</span>
                <span>共 {preview.total} 行数据</span>
              </div>

              {/* Summary cards */}
              <div className="grid grid-cols-2 md:grid-cols-4 gap-3">
                {summaryCards.map((c) => (
                  <div key={c.label} className={`rounded-xl p-4 ${c.bg} border border-white/5`}>
                    <div className={`text-2xl font-bold ${c.color}`}>{c.value}</div>
                    <div className="text-xs text-white/40 mt-1">{c.label}</div>
                  </div>
                ))}
              </div>

              {/* Preview table */}
              <div>
                <div className="text-sm font-medium text-white/70 mb-2 flex items-center gap-2">
                  <FileText size={15} className="text-sky-400" />
                  数据预览（前 {PREVIEW_ROW_LIMIT} 行）
                </div>
                <div className="overflow-x-auto rounded-xl border border-white/5">
                  <table className="w-full text-left text-sm">
                    <thead>
                      <tr className="text-xs font-semibold text-white/40 uppercase tracking-wider bg-black/30">
                        <th className="p-3">行号</th>
                        <th className="p-3">授权QQ</th>
                        <th className="p-3">授权主人</th>
                        <th className="p-3">产品</th>
                        <th className="p-3">有效期</th>
                        <th className="p-3">校验状态</th>
                      </tr>
                    </thead>
                    <tbody className="divide-y divide-white/5">
                      {preview.preview.map((row) => (
                        <tr key={row.row_index} className="hover:bg-white/[0.02]">
                          <td className="p-3 text-white/30 font-mono text-xs">{row.row_index}</td>
                          <td className="p-3 font-mono text-sky-300">{row.qq || '—'}</td>
                          <td className="p-3 text-white/80">{row.owner_name || '—'}</td>
                          <td className="p-3 text-white/80">{row.product_name || '—'}</td>
                          <td className="p-3 text-white/50 font-mono text-xs">
                            {row.expiration_date || row.expiration_raw || '—'}
                          </td>
                          <td className="p-3">
                            {row.errors.length > 0 ? (
                              <div className="flex items-start gap-1.5 text-red-400 text-xs">
                                <XCircle size={14} className="mt-0.5 shrink-0" />
                                <span>{row.errors.join('、')}</span>
                              </div>
                            ) : row.is_duplicate ? (
                              <div className="flex items-center gap-1.5 text-amber-400 text-xs">
                                <AlertTriangle size={14} />
                                <span>重复（已存在）</span>
                              </div>
                            ) : (
                              <div className="flex items-center gap-1.5 text-emerald-400 text-xs">
                                <CheckCircle2 size={14} />
                                <span>正常</span>
                              </div>
                            )}
                          </td>
                        </tr>
                      ))}
                    </tbody>
                  </table>
                </div>
                {preview.total > PREVIEW_ROW_LIMIT && (
                  <p className="text-xs text-white/30 mt-2">
                    仅展示前 {PREVIEW_ROW_LIMIT} 行，完整数据将在确认后导入。
                  </p>
                )}
              </div>

              {/* Invalid rows warning */}
              {preview.invalid_count > 0 && (
                <div className="rounded-lg bg-red-500/10 border border-red-500/20 p-3 text-sm text-red-300 flex items-start gap-2">
                  <AlertTriangle size={16} className="mt-0.5 shrink-0" />
                  <span>
                    有 {preview.invalid_count} 行数据缺失必填字段或格式错误，导入时将自动跳过并计入失败数。
                  </span>
                </div>
              )}

              {/* Duplicate mode */}
              {preview.duplicate_count > 0 && (
                <div className="rounded-xl border border-amber-500/20 bg-amber-500/5 p-4">
                  <div className="text-sm font-medium text-amber-300 mb-3 flex items-center gap-2">
                    <Copy size={15} />
                    检测到 {preview.duplicate_count} 条重复授权（QQ 已存在），请选择处理方式：
                  </div>
                  <div className="grid grid-cols-1 md:grid-cols-2 gap-3">
                    <label
                      className={`flex items-start gap-3 p-3 rounded-lg border cursor-pointer transition ${
                        dupMode === 'skip'
                          ? 'border-sky-500/50 bg-sky-500/10'
                          : 'border-white/10 bg-white/5 hover:bg-white/10'
                      }`}
                    >
                      <input
                        type="radio"
                        name="dupMode"
                        value="skip"
                        checked={dupMode === 'skip'}
                        onChange={() => setDupMode('skip')}
                        className="mt-1 accent-sky-500"
                      />
                      <div>
                        <div className="text-sm text-white font-medium">跳过重复（推荐）</div>
                        <div className="text-xs text-white/40 mt-0.5">
                          已存在的授权不做任何修改，仅导入新授权
                        </div>
                      </div>
                    </label>
                    <label
                      className={`flex items-start gap-3 p-3 rounded-lg border cursor-pointer transition ${
                        dupMode === 'overwrite'
                          ? 'border-amber-500/50 bg-amber-500/10'
                          : 'border-white/10 bg-white/5 hover:bg-white/10'
                      }`}
                    >
                      <input
                        type="radio"
                        name="dupMode"
                        value="overwrite"
                        checked={dupMode === 'overwrite'}
                        onChange={() => setDupMode('overwrite')}
                        className="mt-1 accent-amber-500"
                      />
                      <div>
                        <div className="text-sm text-white font-medium">覆盖重复</div>
                        <div className="text-xs text-white/40 mt-0.5">
                          用 CSV 中的数据覆盖已有授权的主人、产品和有效期
                        </div>
                      </div>
                    </label>
                  </div>
                </div>
              )}
            </div>
          )}

          {/* Step 3: Result */}
          {step === 'result' && result && (
            <div className="space-y-5">
              <div className="text-center py-4">
                <CheckCircle2 className="w-16 h-16 mx-auto text-emerald-400 mb-4" />
                <h4 className="text-xl font-bold text-white">导入完成</h4>
                <p className="text-white/40 text-sm mt-1">处理结果如下</p>
              </div>

              <div className="grid grid-cols-2 md:grid-cols-4 gap-3">
                <div className="rounded-xl p-4 bg-emerald-500/10 border border-emerald-500/20">
                  <div className="text-2xl font-bold text-emerald-400">{result.success_count}</div>
                  <div className="text-xs text-white/40 mt-1">成功导入</div>
                </div>
                <div className="rounded-xl p-4 bg-sky-500/10 border border-sky-500/20">
                  <div className="text-2xl font-bold text-sky-400">{result.inserted_count}</div>
                  <div className="text-xs text-white/40 mt-1">新增授权</div>
                </div>
                <div className="rounded-xl p-4 bg-amber-500/10 border border-amber-500/20">
                  <div className="text-2xl font-bold text-amber-400">
                    {result.skipped_count + (result.updated_count || 0)}
                  </div>
                  <div className="text-xs text-white/40 mt-1">
                    {dupMode === 'skip' ? '跳过重复' : '覆盖更新'}
                  </div>
                </div>
                <div className="rounded-xl p-4 bg-red-500/10 border border-red-500/20">
                  <div className="text-2xl font-bold text-red-400">{result.failed_count}</div>
                  <div className="text-xs text-white/40 mt-1">失败行数</div>
                </div>
              </div>

              {result.updated_count > 0 && (
                <p className="text-xs text-white/40 text-center">
                  其中覆盖更新已有授权 {result.updated_count} 条。
                </p>
              )}

              {result.failures && result.failures.length > 0 && (
                <div className="rounded-xl border border-red-500/20 overflow-hidden">
                  <div className="px-4 py-2.5 bg-red-500/10 text-sm text-red-300 font-medium flex items-center gap-2">
                    <XCircle size={15} />
                    失败明细（{result.failures.length} 条）
                  </div>
                  <div className="max-h-48 overflow-y-auto divide-y divide-white/5">
                    {result.failures.map((f, i) => (
                      <div key={i} className="px-4 py-2.5 text-xs flex items-center gap-3">
                        <span className="text-white/30 font-mono">第 {f.row} 行</span>
                        <span className="text-sky-300 font-mono">{f.qq || '无QQ'}</span>
                        <span className="text-red-400">{f.errors.join('、')}</span>
                      </div>
                    ))}
                  </div>
                </div>
              )}
            </div>
          )}
        </div>

        {/* Footer */}
        <div className="flex gap-3 justify-end p-5 bg-white/5 shrink-0">
          {step === 'upload' && (
            <button onClick={handleClose} className="px-4 py-2 rounded-lg text-sm font-medium text-white/60 hover:text-white hover:bg-white/5 transition">
              取消
            </button>
          )}
          {step === 'preview' && (
            <>
              <button onClick={reset} disabled={loading} className="px-4 py-2 rounded-lg text-sm font-medium text-white/60 hover:text-white hover:bg-white/5 transition disabled:opacity-50">
                重新选择
              </button>
              <button
                onClick={handleConfirm}
                disabled={loading || preview.valid_count === 0}
                className="px-5 py-2 rounded-lg text-sm font-medium text-white bg-sky-500 hover:bg-sky-600 shadow-lg shadow-sky-500/20 transition disabled:opacity-50 disabled:cursor-not-allowed flex items-center gap-2"
              >
                {loading && <Loader2 size={15} className="animate-spin" />}
                确认导入 {preview.valid_count > 0 && `（${preview.valid_count} 条）`}
              </button>
            </>
          )}
          {step === 'result' && (
            <>
              <button onClick={reset} className="px-4 py-2 rounded-lg text-sm font-medium text-white/60 hover:text-white hover:bg-white/5 transition flex items-center gap-1.5">
                <RefreshCw size={15} /> 继续导入
              </button>
              <button
                onClick={handleClose}
                className="px-5 py-2 rounded-lg text-sm font-medium text-white bg-sky-500 hover:bg-sky-600 shadow-lg shadow-sky-500/20 transition"
              >
                完成
              </button>
            </>
          )}
        </div>
      </div>
    </div>
  );
}
