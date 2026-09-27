import { BrowserRouter as Router, Routes, Route, Link, useLocation } from 'react-router-dom';
import { Toaster } from 'react-hot-toast';
import { LayoutGrid, Search, RefreshCw } from 'lucide-react';
import QueryPage from './pages/QueryPage';
import AdminPage from './pages/AdminPage';
import UpdatePage from './pages/UpdatePage';

function NavLink({ to, children, icon: Icon }) {
  const location = useLocation();
  const isActive = location.pathname === to;
  
  return (
    <Link 
      to={to} 
      className={`flex items-center gap-2 px-4 py-2 rounded-full transition-all duration-300 ${
        isActive 
          ? 'bg-sky-500/20 text-sky-300 shadow-[0_0_20px_rgba(14,165,233,0.3)]' 
          : 'text-white/60 hover:text-white hover:bg-white/5'
      }`}
    >
      <Icon size={16} />
      <span className="font-medium">{children}</span>
    </Link>
  );
}

function MainLayout() {
  return (
    <div className="min-h-screen relative overflow-hidden text-sm selection:bg-sky-500/30">
        {/* Dynamic Background */}
        <div className="fixed top-0 left-0 w-full h-full -z-10 bg-[#020617]">
           {/* Gradient Splashes */}
           <div className="absolute top-[-20%] left-[10%] w-[800px] h-[800px] bg-indigo-500/10 rounded-full blur-[120px] animate-pulse-slow"></div>
           <div className="absolute bottom-[-10%] right-[5%] w-[600px] h-[600px] bg-sky-600/10 rounded-full blur-[100px] animate-float"></div>
           
           {/* Grid Pattern */}
           <div className="absolute inset-0 bg-[url('https://grainy-gradients.vercel.app/noise.svg')] opacity-20"></div>
        </div>

        <nav className="fixed top-0 left-0 right-0 z-40 p-4 flex justify-center">
           <div className="glass-card !bg-white/5 !border-white/10 !rounded-full px-2 py-2 flex items-center gap-1 backdrop-blur-xl shadow-2xl">
              <NavLink to="/" icon={Search}>授权查询</NavLink>
              <NavLink to="/update" icon={RefreshCw}>自助更绑</NavLink>
              <NavLink to="/admin" icon={LayoutGrid}>后台管理</NavLink>
           </div>
        </nav>

        <main className="container mx-auto px-4 py-28 relative z-10">
          <Routes>
            <Route path="/" element={<QueryPage />} />
            <Route path="/admin" element={<AdminPage />} />
            <Route path="/update" element={<UpdatePage />} />
          </Routes>
        </main>
        
        <footer className="text-center text-white/20 text-xs pb-8">
           &copy; 2026 星罗授权管理系统 | All Rights Reserved
        </footer>
        
        <Toaster 
          position="top-center" 
          toastOptions={{ 
            style: { 
              background: 'rgba(15, 23, 42, 0.8)', 
              color: '#fff',
              backdropFilter: 'blur(10px)',
              border: '1px solid rgba(255,255,255,0.1)',
              borderRadius: '12px',
              padding: '12px 24px',
            },
            success: { iconTheme: { primary: '#38bdf8', secondary: '#fff' } }
          }} 
        />
    </div>
  );
}

function App() {
  return (
    <Router>
       <MainLayout />
    </Router>
  );
}

export default App;
