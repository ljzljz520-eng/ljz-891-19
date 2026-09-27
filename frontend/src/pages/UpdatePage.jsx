import React, { useState } from 'react';
import axios from 'axios';
import { toast } from 'react-hot-toast';
import { Mail, RefreshCw, Key } from 'lucide-react';

export default function UpdatePage() {
  const [step, setStep] = useState(1);
  const [qq, setQq] = useState('');
  const [code, setCode] = useState('');
  const [newOwner, setNewOwner] = useState('');
  const [cooldown, setCooldown] = useState(0);

  const sendCode = async () => {
    if (!qq) {
      toast.error('请输入QQ号');
      return;
    }
    try {
      // In real scenario, this sends email. In demo, it logs to server console.
      const res = await axios.post('/api/license/send-code', { qq });
      toast.success(res.data.message || '验证码已发送');
      // For convenience in demo, we might show it in console or toast if returned
      if(res.data.mock_code) {
          console.log("Mock Code:", res.data.mock_code);
          toast("测试环境验证码: " + res.data.mock_code, {icon: '🔍'});
      }
      setCooldown(60);
      const timer = setInterval(() => {
        setCooldown(prev => {
          if (prev <= 1) {
            clearInterval(timer);
            return 0;
          }
          return prev - 1;
        });
      }, 1000);
    } catch (err) {
      toast.error('发送失败');
    }
  };

  const handleUpdate = async (e) => {
    e.preventDefault();
    try {
      await axios.post('/api/license/update', { qq, code, owner_name: newOwner });
      toast.success('更绑成功');
      setStep(1); setQq(''); setCode(''); setNewOwner('');
    } catch (err) {
      toast.error('验证失败或验证码过期');
    }
  };

  return (
    <div className="max-w-xl mx-auto mt-10">
      <div className="glass-card p-8">
        <h2 className="text-2xl font-bold mb-6 text-center">自助更绑</h2>
        <form onSubmit={handleUpdate} className="space-y-6">
          
          <div>
            <label className="block text-sm mb-2 text-white/70">请输入授权QQ</label>
            <div className="flex gap-2">
              <input 
                type="text" 
                value={qq} 
                onChange={e => setQq(e.target.value)} 
                className="glass-input flex-1" 
                placeholder="123456789"
              />
              <button 
                type="button"
                onClick={sendCode}
                disabled={cooldown > 0}
                className={`px-4 py-2 rounded-lg font-medium transition ${cooldown > 0 ? 'bg-white/10 text-white/40' : 'bg-sky-500 hover:bg-sky-400 text-white'}`}
              >
                {cooldown > 0 ? `${cooldown}s` : '发送验证码'}
              </button>
            </div>
          </div>

          <div>
             <label className="block text-sm mb-2 text-white/70">邮件验证码</label>
             <div className="relative">
                <input 
                  type="text" 
                  value={code} 
                  onChange={e => setCode(e.target.value)} 
                  className="glass-input w-full pl-10" 
                  placeholder="收到QQ邮箱中的6位验证码"
                />
                <Mail className="absolute left-3 top-2.5 w-4 h-4 text-white/40" />
             </div>
          </div>

          <div>
             <label className="block text-sm mb-2 text-white/70">新授权主人名称</label>
             <div className="relative">
                <input 
                  type="text" 
                  value={newOwner} 
                  onChange={e => setNewOwner(e.target.value)} 
                  className="glass-input w-full pl-10" 
                  placeholder="请输入新的主人名称"
                />
                <RefreshCw className="absolute left-3 top-2.5 w-4 h-4 text-white/40" />
             </div>
          </div>

          <button type="submit" className="tech-button w-full mt-2">确认更改</button>

        </form>
      </div>
    </div>
  );
}
