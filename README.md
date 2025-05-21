# NLWeb for WordPress

A WordPress plugin that adds natural language search capabilities to your WordPress site using the NLWeb framework and MCP (Model Context Protocol) specification.

## Description

NLWeb for WordPress allows you to create a natural language interface for your WordPress site, enabling users to search and interact with your content using natural language queries. It leverages the NLWeb framework developed by Microsoft and is compatible with the Model Context Protocol (MCP), allowing integration with AI assistants like Claude.

### Features

- **Natural Language Search**: Allow users to search your WordPress content using natural language queries.
- **MCP Compatibility**: Connect your WordPress site to AI assistants through the Model Context Protocol.
- **Multiple Vector Databases**: Support for Milvus, ChromaDB, Qdrant, Pinecone, and Weaviate.
- **Multiple Embedding Providers**: Support for OpenAI, Anthropic, Google Gemini, and Ollama (local models).
- **Embedding Caching**: Reduce API costs and improve performance by caching embeddings.
- **Retry Logic**: Automated retry mechanisms for handling temporary API failures.
- **Automatic Model Pulling**: For Ollama models, automatically pull models when needed.
- **Chat Widget**: Add a floating chat widget to your site for easy user interaction.
- **Shortcode Support**: Embed the chat interface anywhere on your site using a shortcode. Fully processes shortcodes in WordPress content to ensure proper display.
- **Admin Dashboard**: Manage settings, ingest content, and control the chat interface from the WordPress admin.
- **Diagnostic Tools**: Built-in tools to test embedding generation and vector database connectivity.

## Installation

1. Download the plugin zip file or clone this repository.
2. Upload the plugin files to the `/wp-content/plugins/nl-wp` directory, or install the plugin through the WordPress plugins screen.
3. Activate the plugin through the 'Plugins' screen in WordPress.
4. Configure the plugin settings through the 'NLWeb' menu in the WordPress admin.

### Requirements

- WordPress 6.0 or higher
- PHP 7.4 or higher
- A vector database (Milvus, ChromaDB, Qdrant, Pinecone, or Weaviate)
- Python 3.7+ with appropriate package(s) installed
- LLM API access (OpenAI, Anthropic, Google Gemini, or Ollama) for text embeddings

## Configuration

### 1. Vector Database Setup

The plugin supports multiple vector databases. Choose and set up one of the following:

#### Milvus

1. **Installation**:
   - Install Milvus locally using Docker:
     ```bash
     docker run -d --name milvus -p 19530:19530 -p 9091:9091 milvusdb/milvus:v2.3.3-latest
     ```
   - Or use a hosted Milvus service (Zilliz Cloud)

2. **Python Requirements**:
   - Install the PyMilvus client: `pip install pymilvus`

3. **Plugin Configuration**:
   - Set Host: `localhost` (or your Milvus host address)
   - Set Port: `19530` (default port)
   - Collection Name: `wordpress_content` (or customize)

#### ChromaDB

1. **Installation**:
   - Install ChromaDB locally:
     ```bash
     pip install chromadb
     ```
   - Run ChromaDB server:
     ```bash
     chroma run --host 0.0.0.0 --port 8000
     ```

2. **Python Requirements**:
   - Ensure chromadb is installed: `pip install chromadb`

3. **Plugin Configuration**:
   - Set Host: `localhost` (or your ChromaDB host address)
   - Set Port: `8000` (default port)
   - Collection Name: `wordpress_content` (or customize)

#### Qdrant

1. **Installation**:
   - Install Qdrant locally using Docker:
     ```bash
     docker run -p 6333:6333 -p 6334:6334 qdrant/qdrant
     ```
   - Or use Qdrant Cloud

2. **Python Requirements**:
   - Install the Qdrant client: `pip install qdrant-client`

3. **Plugin Configuration**:
   - Set Host: `localhost` (or your Qdrant host address)
   - Set Port: `6333` (default port)
   - API Key: Only needed for Qdrant Cloud
   - Collection Name: `wordpress_content` (or customize)

#### Pinecone

1. **Account Setup**:
   - Create a Pinecone account: [https://app.pinecone.io](https://app.pinecone.io)
   - Create a new index with appropriate dimensions (must match your embedding model)

2. **Python Requirements**:
   - Install the Pinecone client: `pip install pinecone-client`

3. **Plugin Configuration**:
   - API Key: From your Pinecone dashboard
   - Environment: Your Pinecone environment (e.g., `us-west4-gcp`)
   - Index Name: The name of your Pinecone index

#### Weaviate

1. **Installation**:
   - Install Weaviate locally using Docker:
     ```bash
     docker run -d --name weaviate -p 8080:8080 semitechnologies/weaviate:1.23.0
     ```
   - Or use Weaviate Cloud Services (WCS)

2. **Python Requirements**:
   - Install the Weaviate client: `pip install weaviate-client`

3. **Plugin Configuration**:
   - Host URL: `http://localhost:8080` (or your Weaviate host URL)
   - API Key: Only needed for Weaviate Cloud
   - Collection Name: `WordpressContent` (must be capitalized)

### 2. Embedding Provider Setup

The plugin supports multiple embedding providers. Choose and set up one of the following:

#### OpenAI

1. **Account Setup**:
   - Create an OpenAI account: [https://platform.openai.com/signup](https://platform.openai.com/signup)
   - Generate an API key: [https://platform.openai.com/api-keys](https://platform.openai.com/api-keys)

2. **Plugin Configuration**:
   - Provider: Select `OpenAI`
   - API Key: Paste your OpenAI API key
   - Model: Choose from available models:
     - `text-embedding-3-small` (1536 dimensions)
     - `text-embedding-3-large` (3072 dimensions)
     - `text-embedding-ada-002` (1536 dimensions, legacy)

#### Anthropic

1. **Account Setup**:
   - Create an Anthropic account: [https://console.anthropic.com/signup](https://console.anthropic.com/signup)
   - Generate an API key from the Anthropic Console

2. **Plugin Configuration**:
   - Provider: Select `Anthropic`
   - API Key: Paste your Anthropic API key
   - Model: Choose from available models:
     - `claude-3-haiku-20240307` (1536 dimensions)
     - `claude-3-sonnet-20240229` (1536 dimensions)
     - `claude-3-opus-20240229` (1536 dimensions)

#### Google Gemini

1. **Account Setup**:
   - Create a Google AI Studio account: [https://aistudio.google.com/](https://aistudio.google.com/)
   - Generate an API key from the Google AI Studio

2. **Plugin Configuration**:
   - Provider: Select `Gemini`
   - API Key: Paste your Google AI API key
   - Model: Choose from available models:
     - `embedding-001` (768 dimensions)
     - `text-embedding-004` (768 dimensions)

#### Ollama (Local)

1. **Installation**:
   - Install Ollama from [https://ollama.com/download](https://ollama.com/download)
   - Start the Ollama service

2. **Plugin Configuration**:
   - Provider: Select `Ollama`
   - Server URL: `http://localhost:11434` (default)
   - Model: Choose from available models (will be pulled automatically if not available):
     - `nomic-embed-text` (768 dimensions, recommended for embedding)
     - Various LLM models that can generate embeddings:
       - Gemma, Llama3, Mistral, Phi3, Qwen, DeepSeek, etc.

### 3. Cache and Error Handling

Configure performance and reliability settings:

1. **Embedding Cache**:
   - Enable Caching: Recommended `Yes` to reduce API calls
   - Cache Expiration: How long to store cached embeddings (default: 1 day)

2. **Error Handling**:
   - Retry Attempts: Number of times to retry failed API calls (default: 3)
   - Retry Delay: Base delay between retries with exponential backoff (default: 1 second)

### 4. Content Ingestion

After setting up your vector database and embedding provider:

1. Go to NLWeb > Content Manager
2. Select the content type (posts, pages, or custom post types)
3. Set the limit and offset (for large sites, ingest in batches)
4. Click "Ingest Content"
5. Wait for the process to complete

### 5. Chat Widget Setup

- Configure the chat widget appearance and behavior in the plugin settings
- Enable the chat widget to display it on your site
- Customize title, placeholder text, position, and color

## Usage

### Chat Widget

Once configured, a chat widget will appear on your site (if enabled). Users can click the widget to open a chat interface and ask questions about your content. The widget ensures proper display of content, handling shortcodes and other WordPress-specific formatting.

### Shortcode

You can add the chat interface to any post or page using the shortcode:

```
[nlwp_chat title="Ask me anything" placeholder="Type your question..." width="100%" height="500px"]
```

### MCP Endpoint

The plugin adds an MCP-compatible endpoint at `/wp-json/nlwp/v1/mcp` that can be used by AI assistants to interact with your site's content.

## Development

This plugin is based on the NLWeb framework developed by Microsoft and extends it with support for multiple vector databases and embedding providers. It includes comprehensive shortcode processing to ensure WordPress content is properly handled and displayed. It uses:

- **Vector Databases**:
  - [Milvus](https://milvus.io/) - Fast, scalable distributed vector database
  - [ChromaDB](https://www.trychroma.com/) - Open-source embedding database
  - [Qdrant](https://qdrant.tech/) - Vector database for similarity search
  - [Pinecone](https://www.pinecone.io/) - Cloud vector database for semantic search
  - [Weaviate](https://weaviate.io/) - Open-source vector database with a GraphQL interface

- **Embedding Providers**:
  - [OpenAI](https://openai.com/) - State-of-the-art embedding models
  - [Anthropic](https://www.anthropic.com/) - Claude's embedding capabilities
  - [Google Gemini](https://ai.google.dev/) - Google's multimodal AI embedding models
  - [Ollama](https://ollama.com/) - Run LLMs and embedding models locally

- **Frameworks & Standards**:
  - [Model Context Protocol (MCP)](https://github.com/microsoft/modelcontextprotocol) - Protocol for AI assistants
  - [WordPress Plugin API](https://developer.wordpress.org/plugins/) - For WordPress integration
  - [Schema.org](https://schema.org/) - For structured data in search results

### Developer Notes

- The plugin uses a factory pattern to create vector database and embedding provider instances.
- Dependency injection is used to inject the embedding provider into the vector database.
- Abstract classes are used to define common interfaces for both vector databases and embedding providers.
- Multi-layered shortcode processing ensures WordPress content is properly handled at ingestion, retrieval, and display stages.
- The plugin supports dynamic dimension detection to adapt to different embedding model dimensions.
- Caching is implemented using WordPress transients for performance optimization.
- Retry logic with exponential backoff is implemented for API requests to improve reliability.
- Diagnostic tools are included for easier troubleshooting and configuration validation.

### File Structure

- `nl-wp.php` - Main plugin file with plugin metadata and initialization
- `includes/` - Core plugin classes
  - `class-nl-wp.php` - Main plugin class that orchestrates all components
  - `class-nl-wp-loader.php` - Handles WordPress action and filter hooks
  - `class-nl-wp-api.php` - Manages REST API endpoints for NLWeb
  - `class-nl-wp-mcp.php` - Implements Model Context Protocol endpoints
  - `class-nl-wp-factory.php` - Factory class for creating service instances
  - `class-nl-wp-embedding-factory.php` - Factory for embedding providers
  - `class-nl-wp-vector-db.php` - Abstract base class for vector databases
  - `embeddings/` - Embedding provider implementations
    - `class-nl-wp-embedding-provider.php` - Abstract base class for embedding
    - `class-nl-wp-openai-provider.php` - OpenAI embedding implementation
    - `class-nl-wp-anthropic-provider.php` - Anthropic embedding implementation
    - `class-nl-wp-gemini-provider.php` - Google Gemini embedding implementation
    - `class-nl-wp-ollama-provider.php` - Ollama (local) embedding implementation
  - `vector-db/` - Vector database implementations
    - `class-nl-wp-milvus.php` - Milvus vector database implementation
    - `class-nl-wp-chroma.php` - ChromaDB vector database implementation
    - `class-nl-wp-qdrant.php` - Qdrant vector database implementation
    - `class-nl-wp-pinecone.php` - Pinecone vector database implementation
    - `class-nl-wp-weaviate.php` - Weaviate vector database implementation
- `admin/` - Admin-specific functionality
  - `class-nl-wp-admin.php` - Admin dashboard and settings
  - `css/` - Admin CSS styles
  - `js/` - Admin JavaScript functionality
- `public/` - Public-facing functionality
  - `css/` - Public CSS styles
  - `js/` - Public JavaScript functionality
  - `partials/` - Template partials for the frontend
    - `chat-widget.php` - Floating chat widget template
    - `chat-shortcode.php` - Embedded chat interface template

## License

This plugin is licensed under the MIT License.

## Credits

This plugin is based on the NLWeb framework developed by Microsoft.

## Support

For support, please visit the [GitHub repository](https://github.com/microsoft/NLWeb) or contact the plugin author.

## Changelog

### Version 1.1.0

- **Enhanced Shortcode Processing**: Fixed issues with shortcode tags appearing in chat responses
- **Improved Embedding Generation**: Added robust cleanup to ensure proper content processing
- **Chat Widget Fixes**: Resolved scrolling issues in the chat interface
- **API Key Management**: Fixed storage of provider-specific API keys
- **MCP Endpoint Improvements**: Enhanced response handling for better compatibility with AI assistants

### Version 1.0.0

- Initial release