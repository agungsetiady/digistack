// assets/js/course-viewer.js

// Mock Data Structure (Akan diganti dengan Fetch REST API dari backend)
const mockCourseData = {
  id: 1,
  title: "Pengembangan REST API PHP",
  modules: [
    {
      id: 101,
      title: "Modul 1: Pengenalan REST API",
      topics: [
        { id: 1, title: "1.1 Konsep Basic REST", read_time: 10, content: "<p>REST (Representational State Transfer) adalah standar arsitektur web berbasis HTTP...</p>", completed: true },
        { id: 2, title: "1.2 HTTP Methods & Response Code", read_time: 15, content: "<p>HTTP Method seperti GET, POST, PUT, DELETE menentukan aksi pada resource...</p>", completed: false }
      ]
    },
    {
      id: 102,
      title: "Modul 2: Database & Routing",
      topics: [
        { id: 3, title: "2.1 Design Skema Database", read_time: 20, content: "<p>Skema database yang baik mendukung efisiensi response REST API...</p>", completed: false }
      ]
    }
  ]
};

let currentTopicId = 1;

document.addEventListener('DOMContentLoaded', () => {
  initSidebarToggle();
  renderCourseSidebar(mockCourseData);
  loadTopic(currentTopicId);
});

// 1. Toggle Sidebar Mobile / Desktop
function initSidebarToggle() {
  const toggleBtn = document.getElementById('toggle-sidebar');
  const sidebar = document.getElementById('sidebar');

  toggleBtn?.addEventListener('click', () => {
    sidebar.classList.toggle('-ml-80');
  });
}

// 2. Render Accordion Sidebar
function renderCourseSidebar(course) {
  document.getElementById('course-title').innerText = course.title;
  const container = document.getElementById('module-accordion-container');
  container.innerHTML = '';

  course.modules.forEach((module, index) => {
    const moduleEl = document.createElement('div');
    
    const topicsHtml = module.topics.map(t => `
      <a href="javascript:void(0)" onclick="loadTopic(${t.id})" 
         id="topic-btn-${t.id}"
         class="px-6 py-2.5 flex items-center justify-between text-xs font-medium transition ${t.id === currentTopicId ? 'bg-blue-50 dark:bg-blue-900/30 text-blue-600 dark:text-blue-400 border-l-4 border-blue-600' : 'text-gray-600 dark:text-gray-400 hover:bg-gray-50 dark:hover:bg-gray-700/50'}">
        <span class="flex items-center gap-2 truncate">
          ${t.completed ? '<svg class="w-4 h-4 text-green-500 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/></svg>' : ''}
          ${t.title}
        </span>
        <span class="text-gray-400 text-[10px] ml-2 flex-shrink-0">${t.read_time}m</span>
      </a>
    `).join('');

    moduleEl.innerHTML = `
      <div>
        <button onclick="toggleModule(${module.id})" class="w-full px-4 py-3 flex items-center justify-between bg-gray-50 dark:bg-gray-800/50 hover:bg-gray-100 dark:hover:bg-gray-700/40 text-left font-semibold text-xs text-gray-700 dark:text-gray-200">
          <span>${module.title}</span>
          <svg id="arrow-${module.id}" class="w-4 h-4 transform transition-transform duration-200 ${index === 0 ? 'rotate-180' : ''}" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
        </button>
        <div id="module-body-${module.id}" class="${index === 0 ? '' : 'hidden'} py-1">
          ${topicsHtml}
        </div>
      </div>
    `;

    container.appendChild(moduleEl);
  });
}

function toggleModule(moduleId) {
  const body = document.getElementById(`module-body-${moduleId}`);
  const arrow = document.getElementById(`arrow-${moduleId}`);
  body.classList.toggle('hidden');
  arrow.classList.toggle('rotate-180');
}

// 3. Load & Render Content Topik
function loadTopic(topicId) {
  currentTopicId = topicId;
  let foundTopic = null;
  let foundModule = null;

  mockCourseData.modules.forEach(m => {
    const t = m.topics.find(top => top.id === topicId);
    if (t) {
      foundTopic = t;
      foundModule = m;
    }
  });

  if (!foundTopic) return;

  // Render UI Content
  document.getElementById('current-topic-breadcrumb').innerText = `${foundModule.title} / ${foundTopic.title}`;
  document.getElementById('topic-module-tag').innerText = foundModule.title;
  document.getElementById('topic-title').innerText = foundTopic.title;
  document.getElementById('topic-read-time').innerText = foundTopic.read_time;
  document.getElementById('topic-body').innerHTML = foundTopic.content;

  // Re-render sidebar active state
  renderCourseSidebar(mockCourseData);
}

// 4. Action Complete Topic
function toggleCompleteTopic() {
  // Logic update progres topik (dikirim ke REST API nantinya)
  console.log('Topic ID', currentTopicId, 'marked as completed');
  // Pindah ke topik berikutnya jika ada
}