import React, { useState, useRef } from 'react';
import axios from 'axios';
import { toast } from 'react-hot-toast';
import {
  X, Upload, FileSpreadsheet, CheckCircle2, XCircle, AlertTriangle,
  Download, ArrowRight, RotateCcw, Copy, SkipForward
} from 'lucide-react';

// CSV 导入三步：1 选择文件并预览 -> 2 校验结果与重复策略 -> 3 导入结果
export default function ImportLicensesModal({ isOpen, onClose, onImported }) {
  const [step, setStep] = useState(1);
  const [file, setFile] = useState(null);
  const [uploading, setUploading] = useState(false);
  const [importing, setImporting] = useState(false);
  const [previewData, setPreviewData] = useState(null); // {total,valid,invalid,duplicates,preview,rows}
  const [mode, setMode] = useState('skip'); // skip | overwrite
  const [result, setResult] = useState(null);
  const [dragOver, setDragOver] = useState(false);
  const fileInputRef = useRef(null);

  const reset = () => {
    setStep(1); setFile(null); setPreviewData(null);
    setMode('skip'); setResult(null);
    if (fileInputRef.current) fileInputRef.current.value = '';
  };

  const handleClose = () => {
    reset();
    onClose();
  };

  const pickFile = (f) => {
    if (!f) return;
    if (!/\.(csv|txt)$/i.test(f.name)) {
      toast.error('请选择 CSV 文件（.csv）');
      return;
    }
    setFile(f);
  };

  // Step 1 -> 2: 上传并预览
  const handlePreview = async () => {
    if (!file) { toast.error('请先选择 CSV 文件'); return; }
    setUploading(true);
    try {
      const form = new FormData();
      form.append('file', file);
      const res = await axios.post('/api/license/import-preview', form, {
        headers: { 'Content-Type': 'multipart/form-data' }
      });
      setPreviewData(res.data);
      setStep(2);
    } catch (err) {
      toast.error('解析失败：' + (err.response?.data?.message || '网络错误'));
    } finally {
      setUploading(false);
    }
  };

  // Step 2 -> 3: 确认写入
  const handleConfirmImport = async () => {
    if (!previewData) return;
    if (previewData.invalid > 0) {
      toast.error('存在缺失必填项的记录，请修正 CSV 后重新上传');
      return;
    }
    setImporting(true);
    try {
      const res = await axios.post('/api/license/import', {
        rows: previewData.rows,
        mode
      });
      setResult(res.data);
      setStep(3);
      if (res.data.success > 0) {
        onImported?.(); // 刷新列表
      }
    } catch (err) {
      toast.error('导入失败：' + (err.response?.data?.message || '网络错误'));
    } finally {
      setImporting(false);
    }
  };

  const downloadTemplate = () => {
    const bom = '﻿';
    const content = bom + [
      '授权QQ,授权主人,所属产品,授权上级,有效期',
      '123456789,张三,超级授权系统VIP版,总代理,2026-12-31 23:59:59',
      '987654321,李四,企业级管理后台,核心代理,2026/12/31',
      '11111,王五,测试产品,官方,2026年12月31日'
    ].join('\n');
    const blob = new Blob([content], { type: 'text/csv;charset=utf-8;' });
    const url = URL.createObjectURL(blob);
    const a = document.createElement('a');
    a.href = url;
    a.download = '授权导入模板.csv';
    a.click();
    URL.revokeObjectURL(url);
  };

  if (!isOpen) return null;

  return (
    <div className="fixed inset-0 z-50 flex items-center justify-center p-4">
      <div className="absolute inset-0 bg-black/60 backdrop-blur-sm" onClick={importing || uploading ? undefined : handleClose}></div>

      <div className="relative w-full max-w-3xl bg-[#0f172a] border border-white/10 rounded-2xl shadow-2xl animate-fade-in-up overflow-hidden">
        {/* Header */}
        <div className="flex justify-between items-center p-5 border-b border-white/5">
          <h3 className="text-lg font-semibold text-white flex items-center gap-2">
            <FileSpreadsheet className="text-sky-400 w-5 h-5" />
            CSV 批量导入授权
            <span className="text-xs text-white/30 ml-2">
              {step === 1 ? '① 上传文件' : step === 2 ? '② 预览确认' : '③ 导入结果'}
            </span>
          </h3>
          <button onClick={handleClose} disabled={importing || uploading}
            className="text-white/40 hover:text-white transition disabled:opacity-30">
            <X size={20} />
          </button>
        </div>

        <div className="p-6 max-h-[70vh] overflow-y-auto">
          {/* ===== Step 1: 上传 ===== */}
          {step === 1 && (
            <div className="space-y-5">
              <div
                onDragOver={(e) => { e.preventDefault(); setDragOver(true); }}
                onDragLeave={() => setDragOver(false)}
                onDrop={(e) => {
                  e.preventDefault(); setDragOver(false);
                  pickFile(e.dataTransfer.files[0]);
                }}
                onClick={() => fileInputRef.current?.click()}
                className={`border-2 border-dashed rounded-xl p-10 text-center cursor-pointer transition ${
                  dragOver ? 'border-sky-400 bg-sky-500/10' : 'border-white/15 hover:border-sky-400/50 hover:bg-white/[0.02]'
                }`}
              >
                <Upload className="w-10 h-10 mx-auto text-sky-400/70 mb-3" />
                <p className="text-white/80 text-sm font-medium">
                  {file ? file.name : '点击选择或拖拽 CSV 文件到此处'}
                </p>
                <p className="text-white/30 text-xs mt-2">支持 UTF-8 / GBK 编码，单文件最大 10MB</p>
                <input
                  ref={fileInputRef}
                  type="file"
                  accept=".csv,text/csv,text/plain"
                  className="hidden"
                  onChange={(e) => pickFile(e.target.files?.[0])}
                />
              </div>

              <div className="rounded-lg bg-white/[0.03] border border-white/5 p-4 text-xs text-white/50 space-y-1.5">
                <p className="text-white/70 font-semibold mb-2 flex items-center gap-1.5">
                  <AlertTriangle size={13} className="text-amber-400" /> 格式要求
                </p>
                <p>· 首行为表头，列顺序支持：<span className="text-sky-300">授权QQ、授权主人、所属产品、授权上级、有效期</span></p>
                <p>· 授权QQ / 授权主人 / 所属产品 / 有效期 为必填项；上级留空时自动填“官方”</p>
                <p>· 有效期支持 2026-12-31、2026/12/31、2026年12月31日，纯日期默认 23:59:59 到期</p>
                <p>· 系统按“授权QQ + 产品”识别重复记录，导入时可选择跳过或覆盖</p>
              </div>

              <button onClick={downloadTemplate}
                className="text-xs text-sky-400 hover:text-sky-300 flex items-center gap-1.5 transition">
                <Download size={13} /> 下载 CSV 导入模板
              </button>
            </div>
          )}

          {/* ===== Step 2: 预览与校验 ===== */}
          {step === 2 && previewData && (
            <div className="space-y-5">
              {/* 统计卡片 */}
              <div className="grid grid-cols-2 sm:grid-cols-4 gap-3">
                <StatCard label="总行数" value={previewData.total} color="text-white" />
                <StatCard label="校验通过" value={previewData.valid} color="text-green-400"
                  icon={<CheckCircle2 size={15} />} />
                <StatCard label="缺失/错误" value={previewData.invalid} color={previewData.invalid ? 'text-red-400' : 'text-white/50'}
                  icon={previewData.invalid ? <XCircle size={15} /> : null} />
                <StatCard label="重复记录" value={previewData.duplicates} color={previewData.duplicates ? 'text-amber-400' : 'text-white/50'}
                  icon={previewData.duplicates ? <AlertTriangle size={15} /> : null} />
              </div>

              {/* 预览表格 */}
              <div>
                <p className="text-xs text-white/40 mb-2">
                  数据预览（前 {previewData.preview.length} 行 / 共 {previewData.total} 行）
                </p>
                <div className="overflow-x-auto rounded-lg border border-white/5">
                  <table className="w-full text-xs text-left">
                    <thead>
                      <tr className="bg-black/30 text-white/40 uppercase tracking-wider">
                        <th className="p-2.5 w-10">#</th>
                        <th className="p-2.5">授权QQ</th>
                        <th className="p-2.5">授权主人</th>
                        <th className="p-2.5">产品</th>
                        <th className="p-2.5">上级</th>
                        <th className="p-2.5">有效期</th>
                        <th className="p-2.5">检查</th>
                      </tr>
                    </thead>
                    <tbody className="divide-y divide-white/5">
                      {previewData.preview.map((row, i) => (
                        <tr key={i} className={row.errors.length || row.duplicate ? 'bg-amber-500/[0.04]' : ''}>
                          <td className="p-2.5 text-white/30 font-mono">{i + 1}</td>
                          <td className="p-2.5 font-mono text-sky-300 whitespace-nowrap">{row.qq || <EmptyCell />}</td>
                          <td className="p-2.5 whitespace-nowrap">{row.owner_name || <EmptyCell />}</td>
                          <td className="p-2.5 max-w-[160px] truncate">{row.product_name || <EmptyCell />}</td>
                          <td className="p-2.5 whitespace-nowrap">{row.upline}</td>
                          <td className="p-2.5 font-mono whitespace-nowrap">{row.expiration_date || <EmptyCell />}</td>
                          <td className="p-2.5 whitespace-nowrap">
                            {row.errors.length > 0 ? (
                              <span className="inline-flex items-center gap-1 text-red-400" title={row.errors.join('；')}>
                                <XCircle size={13} /> {row.errors[0]}
                              </span>
                            ) : row.duplicate ? (
                              <span className="inline-flex items-center gap-1 text-amber-400">
                                <Copy size={13} /> 重复
                              </span>
                            ) : (
                              <span className="inline-flex items-center gap-1 text-green-400">
                                <CheckCircle2 size={13} /> 通过
                              </span>
                            )}
                          </td>
                        </tr>
                      ))}
                    </tbody>
                  </table>
                </div>
              </div>

              {previewData.invalid > 0 && (
                <div className="rounded-lg bg-red-500/10 border border-red-500/20 p-3.5 text-xs text-red-300 flex items-start gap-2">
                  <XCircle size={15} className="mt-0.5 shrink-0" />
                  <span>有 {previewData.invalid} 行缺少必填项（授权QQ、授权主人、产品或有效期），请修正后重新上传，否则无法导入。</span>
                </div>
              )}

              {/* 重复策略 */}
              {previewData.duplicates > 0 && (
                <div className="rounded-lg bg-amber-500/[0.07] border border-amber-500/20 p-4">
                  <p className="text-xs font-semibold text-amber-300 mb-3 flex items-center gap-1.5">
                    <AlertTriangle size={14} /> 检测到 {previewData.duplicates} 条重复记录（授权QQ + 产品相同），请选择处理方式：
                  </p>
                  <div className="flex flex-col sm:flex-row gap-3">
                    <label className={`flex-1 flex items-start gap-2.5 p-3 rounded-lg border cursor-pointer transition text-xs ${
                      mode === 'skip' ? 'border-sky-500/50 bg-sky-500/10' : 'border-white/10 hover:border-white/25'
                    }`}>
                      <input type="radio" name="dupMode" checked={mode === 'skip'}
                        onChange={() => setMode('skip')} className="mt-0.5 accent-sky-500" />
                      <span>
                        <span className="text-white/80 font-medium flex items-center gap-1"><SkipForward size={13} /> 跳过重复</span>
                        <span className="text-white/40 mt-1 block">保留库中原有记录，仅导入新数据</span>
                      </span>
                    </label>
                    <label className={`flex-1 flex items-start gap-2.5 p-3 rounded-lg border cursor-pointer transition text-xs ${
                      mode === 'overwrite' ? 'border-sky-500/50 bg-sky-500/10' : 'border-white/10 hover:border-white/25'
                    }`}>
                      <input type="radio" name="dupMode" checked={mode === 'overwrite'}
                        onChange={() => setMode('overwrite')} className="mt-0.5 accent-sky-500" />
                      <span>
                        <span className="text-white/80 font-medium flex items-center gap-1"><RotateCcw size={13} /> 覆盖更新</span>
                        <span className="text-white/40 mt-1 block">用 CSV 中的主人、上级、有效期覆盖旧记录</span>
                      </span>
                    </label>
                  </div>
                </div>
              )}
            </div>
          )}

          {/* ===== Step 3: 结果 ===== */}
          {step === 3 && result && (
            <div className="space-y-5">
              <div className="text-center py-3">
                <div className={`w-14 h-14 rounded-full flex items-center justify-center mx-auto mb-3 ${
                  result.failed > 0 ? 'bg-amber-500/15 text-amber-400' : 'bg-green-500/15 text-green-400'
                }`}>
                  {result.failed > 0 ? <AlertTriangle size={28} /> : <CheckCircle2 size={28} />}
                </div>
                <p className="text-white font-bold text-lg">
                  {result.failed > 0 ? '导入完成，部分记录失败' : '导入完成'}
                </p>
              </div>

              <div className="grid grid-cols-2 sm:grid-cols-4 gap-3">
                <StatCard label="总计" value={result.total} color="text-white" />
                <StatCard label="成功" value={result.success} color="text-green-400"
                  sub={`新增 ${result.inserted} / 覆盖 ${result.overwritten}`} />
                <StatCard label="跳过" value={result.skipped} color="text-white/60" />
                <StatCard label="失败" value={result.failed} color={result.failed ? 'text-red-400' : 'text-white/50'} />
              </div>

              {result.failures.length > 0 && (
                <div className="rounded-lg border border-red-500/20 overflow-hidden">
                  <p className="text-xs font-semibold text-red-300 px-4 py-2.5 bg-red-500/10">失败明细</p>
                  <div className="max-h-48 overflow-y-auto divide-y divide-white/5">
                    {result.failures.map((f, i) => (
                      <div key={i} className="px-4 py-2 text-xs flex justify-between gap-4">
                        <span className="text-white/50 font-mono shrink-0">第 {f.line} 行{f.qq ? ` · QQ ${f.qq}` : ''}</span>
                        <span className="text-red-300 text-right">{f.reason}</span>
                      </div>
                    ))}
                  </div>
                </div>
              )}
            </div>
          )}
        </div>

        {/* Footer */}
        <div className="flex gap-3 justify-end p-5 bg-white/5 border-t border-white/5">
          {step === 1 && (
            <>
              <button onClick={handleClose}
                className="px-4 py-2 rounded-lg text-sm font-medium text-white/60 hover:text-white hover:bg-white/5 transition">
                取消
              </button>
              <button onClick={handlePreview} disabled={!file || uploading}
                className="tech-button !py-2 !text-sm disabled:opacity-40 disabled:cursor-not-allowed flex items-center gap-2">
                {uploading ? '解析中...' : <>预览检查 <ArrowRight size={15} /></>}
              </button>
            </>
          )}
          {step === 2 && (
            <>
              <button onClick={reset}
                className="px-4 py-2 rounded-lg text-sm font-medium text-white/60 hover:text-white hover:bg-white/5 transition flex items-center gap-1.5">
                <RotateCcw size={14} /> 重新选择
              </button>
              <button
                onClick={handleConfirmImport}
                disabled={importing || previewData.invalid > 0 || previewData.valid === 0}
                className="tech-button !py-2 !text-sm disabled:opacity-40 disabled:cursor-not-allowed flex items-center gap-2">
                {importing ? '写入中...' : <>确认导入{previewData.duplicates > 0 ? `（${mode === 'skip' ? '跳过' : '覆盖'}重复）` : ''}</>}
              </button>
            </>
          )}
          {step === 3 && (
            <button onClick={handleClose}
              className="tech-button !py-2 !text-sm">
              完成
            </button>
          )}
        </div>
      </div>
    </div>
  );
}

function StatCard({ label, value, color, icon, sub }) {
  return (
    <div className="rounded-lg bg-white/[0.03] border border-white/5 p-3">
      <p className="text-[11px] text-white/40 flex items-center gap-1">{icon}{label}</p>
      <p className={`text-xl font-bold mt-1 ${color}`}>{value}</p>
      {sub && <p className="text-[10px] text-white/30 mt-0.5">{sub}</p>}
    </div>
  );
}

function EmptyCell() {
  return <span className="text-red-400/80 italic">缺失</span>;
}
