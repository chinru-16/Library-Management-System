// app.js — lightweight UI interactions and AJAX to data.php
const api = (data, method='POST') => {
    const opts = { method, headers: {} };
    if (method === 'POST' && !(data instanceof FormData)) {
      opts.headers['Content-Type'] = 'application/x-www-form-urlencoded';
      opts.body = new URLSearchParams(data);
    } else {
      opts.body = data;
    }
    return fetch('data.php', opts).then(r => {
      // some endpoints (backup) return non-json — try-catch
      return r.json().catch(()=>({error:'invalid_response'}));
    });
  };
  
  document.addEventListener('DOMContentLoaded', () => {
    const loginForm = document.getElementById('loginForm');
    const registerForm = document.getElementById('registerForm');
    const authSection = document.getElementById('authSection');
    const memberSection = document.getElementById('memberSection');
    const searchInput = document.getElementById('searchInput');
    const bookTable = document.querySelector('#bookTable tbody');
    const filterCategory = document.getElementById('filterCategory');
    const accountInfo = document.getElementById('accountInfo');
    const logoutBtn = document.getElementById('logoutBtn');
    const myBorrowed = document.getElementById('myBorrowed');
    const adminLink = document.getElementById('adminLink');
  
    // login
    loginForm.addEventListener('submit', (e) => {
      e.preventDefault();
      const form = new FormData(loginForm);
      form.append('action','login');
      api(form).then(res => {
        if (res.ok) {
          showMemberUI(res.user);
        } else alert('Login failed: ' + (res.error||''));
      });
    });
  
    // register
    registerForm.addEventListener('submit', (e) => {
      e.preventDefault();
      const form = new FormData(registerForm);
      form.append('action','register');
      api(form).then(res => {
        if (res.ok) alert('Registered. Please login.');
        else alert('Register failed: ' + (res.error||''));
      });
    });
  
    logoutBtn?.addEventListener('click', () => {
      const fd = new FormData(); fd.append('action','logout');
      api(fd).then(()=> { location.reload(); });
    });
  
    function showMemberUI(user) {
      authSection.classList.add('hidden');
      memberSection.classList.remove('hidden');
      accountInfo.innerHTML = `<strong>${user.name}</strong><br>${user.email}<br>Role: ${user.role}`;
      if (user.role === 'admin') adminLink.classList.remove('hidden');
      loadCategories();
      loadBooks();
      loadMyBorrowed();
    }
  
    // attempt to fetch current session user by calling a protected endpoint (my_borrowed) — if returns login_required we show auth
    api(new URLSearchParams({action:'my_borrowed'}), 'GET').then(res => {
      if (res.ok && res.borrowed !== undefined) {
        // session exists; fetch user info by login endpoint not available — prompt server to expose user? Simpler: show member UI with placeholder
        // Fetch account info by calling a simple protected route? For now rely on borrowed info to indicate logged in.
        showMemberUI({ name:'Member', email:'', role: (res.role||'student') });
        // fill borrowed
        renderBorrowed(res.borrowed);
      }
    }).catch(()=>{});
  
    // search and render books
    searchInput?.addEventListener('input', () => loadBooks());
    filterCategory?.addEventListener('change', () => loadBooks());
  
    function loadCategories(){
      fetch('data.php?action=list_categories').then(r=>r.json()).then(res=>{
        if(res.ok){
          filterCategory.innerHTML = '<option value="">All categories</option>';
          res.categories.forEach(c => {
            const o = document.createElement('option'); o.value = c.category_name; o.textContent = c.category_name;
            filterCategory.appendChild(o);
          });
        }
      });
    }
  
    function loadBooks(){
      const q = searchInput.value || '';
      const cat = filterCategory.value || '';
      fetch(`data.php?action=search_books&q=${encodeURIComponent(q)}&category=${encodeURIComponent(cat)}`).then(r=>r.json()).then(res=>{
        if(!res.ok) return;
        bookTable.innerHTML = '';
        res.books.forEach(b => {
          const tr = document.createElement('tr');
          const coverTd = document.createElement('td');
          const img = document.createElement('img');
          img.className = 'cover';
          img.src = b.cover_image ? b.cover_image : 'https://via.placeholder.com/50x70?text=No+Cover';
          coverTd.appendChild(img);
          tr.appendChild(coverTd);
          tr.innerHTML += `<td>${b.title}</td><td>${b.author}</td><td>${b.publication_year}</td><td>${b.status}</td>`;
          const actionTd = document.createElement('td');
          const borrowBtn = document.createElement('button');
          borrowBtn.textContent = (b.status === 'available') ? 'Borrow' : (b.status === 'reserved' ? 'Reserve' : 'Not available');
          borrowBtn.disabled = (b.status === 'borrowed');
          borrowBtn.addEventListener('click', () => {
            if (b.status === 'available') doBorrow(b.book_id);
            else doReserve(b.book_id);
          });
          actionTd.appendChild(borrowBtn);
          tr.appendChild(actionTd);
          bookTable.appendChild(tr);
        });
      });
    }
  
    function doBorrow(book_id){
      if (!confirm('Borrow this book?')) return;
      const fd = new FormData(); fd.append('action','borrow'); fd.append('book_id',book_id);
      api(fd).then(res => {
        if (res.ok) { alert('Borrowed! Due: ' + res.due_date); loadBooks(); loadMyBorrowed(); }
        else if (res.error === 'login_required') alert('Please login first.');
        else alert('Error: ' + (res.error||''));
      });
    }
  
    function doReserve(book_id){
      if (!confirm('Reserve this book?')) return;
      const fd = new FormData(); fd.append('action','reserve'); fd.append('book_id',book_id);
      api(fd).then(res => {
        if (res.ok) { alert('Reserved!'); loadBooks(); }
        else alert('Error: ' + (res.error||''));
      });
    }
  
    function loadMyBorrowed(){
      fetch('data.php?action=my_borrowed').then(r=>r.json()).then(res=>{
        if(res.ok) renderBorrowed(res.borrowed);
      });
    }
  
    function renderBorrowed(list){
      myBorrowed.innerHTML = '';
      if(!list || list.length===0) { myBorrowed.textContent = 'No borrowed books'; return; }
      const ul = document.createElement('ul');
      list.forEach(t => {
        const li = document.createElement('li');
        const status = t.status;
        li.innerHTML = `<strong>${t.title}</strong> — due ${t.due_date} — status: ${status} ${t.fine ? '<br>Fine: ' + t.fine : ''}`;
        if (status !== 'returned') {
          const btn = document.createElement('button');
          btn.textContent = 'Return';
          btn.addEventListener('click', ()=> {
            if(!confirm('Return this book?')) return;
            const fd = new FormData(); fd.append('action','return'); fd.append('transaction_id', t.transaction_id);
            api(fd).then(res => {
              if(res.ok) { alert('Returned. Fine: ' + res.fine); loadBooks(); loadMyBorrowed(); }
              else alert('Error: ' + (res.error||''));
            });
          });
          li.appendChild(document.createElement('br'));
          li.appendChild(btn);
        }
        ul.appendChild(li);
      });
      myBorrowed.appendChild(ul);
    }
  });  